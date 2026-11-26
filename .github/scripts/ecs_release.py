"""Prepare and verify a release against the currently running ECS task."""
import copy
import json
import os
import re
import subprocess
import sys
from pathlib import Path


def aws(*arguments):
    result = subprocess.check_output(
        ["aws", *arguments, "--region", os.environ["AWS_REGION"], "--output", "json"],
        text=True,
    )
    return json.loads(result)


def expected_images():
    registry = f"610646412385.dkr.ecr.{os.environ['AWS_REGION']}.amazonaws.com"
    images = {}
    for prefix in ("PHP", "NGINX"):
        image = os.environ[f"{prefix}_IMAGE"]
        repository = os.environ[f"ECR_{prefix}_REPOSITORY"]
        container = os.environ[f"ECS_{prefix}_CONTAINER"]
        if not re.fullmatch(re.escape(f"{registry}/{repository}@") + r"sha256:[0-9a-f]{64}", image):
            raise ValueError(f"{prefix}_IMAGE must use the expected ECR repository and a digest")
        if container in images:
            raise ValueError("Application containers must have distinct names")
        images[container] = image
    return images


def get_service():
    result = aws(
        "ecs", "describe-services", "--cluster", os.environ["ECS_CLUSTER"],
        "--services", os.environ["ECS_SERVICE"],
    )
    services = result.get("services", [])
    if result.get("failures") or len(services) != 1 or services[0]["status"] != "ACTIVE":
        raise ValueError("Expected one ACTIVE ECS service")
    return services[0]


def require_stable_revision(service, expected_arn):
    deployments = service.get("deployments", [])
    if service["taskDefinition"] != expected_arn:
        raise ValueError("The service is on another task revision; the release may have rolled back")
    if len(deployments) != 1:
        raise ValueError("The service still has multiple deployments")
    deployment = deployments[0]
    if (deployment["taskDefinition"] != expected_arn
            or deployment["status"] != "PRIMARY"
            or deployment.get("rolloutState") != "COMPLETED"):
        raise ValueError("The requested revision has not completed its rollout")
    if (service["desiredCount"] < 1 or service["pendingCount"] != 0
            or service["runningCount"] != service["desiredCount"]):
        raise ValueError("The service has not reached its active desired count")


def render_task_definition(task, images, supported_fields, family):
    if task["family"] != family:
        raise ValueError("The active task belongs to a different task family")
    request = {key: copy.deepcopy(value) for key, value in task.items() if key in supported_fields}
    names = [container["name"] for container in request["containerDefinitions"]]
    if any(names.count(name) != 1 for name in images):
        raise ValueError("Expected each application container exactly once")
    for container in request["containerDefinitions"]:
        if container["name"] in images:
            container["image"] = images[container["name"]]
    return request


def check_task_images(task, images):
    actual = {container["name"]: container["image"] for container in task["containerDefinitions"]}
    if any(actual.get(name) != image for name, image in images.items()):
        raise ValueError("The deployed application images do not match the release digests")


def prepare():
    images = expected_images()
    service = get_service()
    require_stable_revision(service, service["taskDefinition"])
    if service.get("deploymentController", {}).get("type") != "ECS":
        raise ValueError("Only ECS rolling deployments are supported")
    breaker = service.get("deploymentConfiguration", {}).get("deploymentCircuitBreaker", {})
    if not breaker.get("enable") or not breaker.get("rollback"):
        raise ValueError("Enable the Terraform ECS deployment circuit breaker before releasing")
    task = aws("ecs", "describe-task-definition", "--task-definition", service["taskDefinition"])["taskDefinition"]
    supported = aws("ecs", "register-task-definition", "--generate-cli-skeleton", "input")
    request = render_task_definition(task, images, supported, os.environ["ECS_TASK_FAMILY"])
    Path(os.environ["TASK_DEFINITION_FILE"]).write_text(json.dumps(request, indent=2) + "\n")
    record = {
        "cluster": os.environ["ECS_CLUSTER"],
        "service": os.environ["ECS_SERVICE"],
        "previous_task_definition": service["taskDefinition"],
        "images": images,
        "status": "prepared",
    }
    Path(os.environ["DEPLOYMENT_RECORD"]).write_text(json.dumps(record, indent=2) + "\n")
    print(f"Prepared images using active revision {service['taskDefinition']}")


def verify():
    expected_arn = os.environ["EXPECTED_TASK_DEFINITION"]
    images = expected_images()
    service = get_service()
    require_stable_revision(service, expected_arn)
    task = aws("ecs", "describe-task-definition", "--task-definition", expected_arn)["taskDefinition"]
    check_task_images(task, images)
    record_file = Path(os.environ["DEPLOYMENT_RECORD"])
    record = json.loads(record_file.read_text())
    if record["images"] != images:
        raise ValueError("Release images changed after task preparation")
    record.update(task_definition=expected_arn, status="completed")
    record_file.write_text(json.dumps(record, indent=2) + "\n")
    with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as summary:
        summary.write("## ECS deployment completed\n\n")
        summary.write(f"Service: {record['cluster']}/{record['service']}\n\n")
        summary.write(f"Previous revision: {record['previous_task_definition']}\n\n")
        summary.write(f"Current revision: {expected_arn}\n\n")
        for name, image in images.items():
            summary.write(f"- {name}: {image}\n")
    print(f"Verified completed rollout of {expected_arn}")


if __name__ == "__main__":
    try:
        if len(sys.argv) != 2 or sys.argv[1] not in ("prepare", "verify"):
            raise ValueError("Usage: ecs_release.py prepare|verify")
        {"prepare": prepare, "verify": verify}[sys.argv[1]]()
    except (ValueError, KeyError, subprocess.CalledProcessError) as error:
        print(f"::error::{error}", file=sys.stderr)
        sys.exit(1)
