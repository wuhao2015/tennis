import copy
import os
import unittest
from unittest.mock import patch

from ecs_release import check_task_images, expected_images, render_task_definition, require_stable_revision


class EcsReleaseTest(unittest.TestCase):
    def setUp(self):
        self.images = {"php-fpm": "php@sha256:" + "a" * 64, "nginx": "nginx@sha256:" + "b" * 64}
        self.task = {
            "family": "tennis", "taskDefinitionArn": "task:4", "revision": 4,
            "taskRoleArn": "task-role", "executionRoleArn": "execution-role",
            "containerDefinitions": [
                {"name": "php-fpm", "image": "old-php", "secrets": [{"name": "APP_KEY", "valueFrom": "secret-arn"}]},
                {"name": "nginx", "image": "old-nginx", "portMappings": [{"containerPort": 80}]},
                {"name": "appconfig-agent", "image": "agent", "essential": True},
            ],
        }
        self.fields = {"family", "taskRoleArn", "executionRoleArn", "containerDefinitions"}
        self.service = {
            "taskDefinition": "task:5", "desiredCount": 2, "runningCount": 2, "pendingCount": 0,
            "deployments": [{"taskDefinition": "task:5", "status": "PRIMARY", "rolloutState": "COMPLETED"}],
        }

    def test_render_changes_only_application_images(self):
        original = copy.deepcopy(self.task)
        rendered = render_task_definition(self.task, self.images, self.fields, "tennis")
        for container in rendered["containerDefinitions"]:
            if container["name"] in self.images:
                self.assertEqual(container["image"], self.images[container["name"]])
                container["image"] = next(c["image"] for c in original["containerDefinitions"] if c["name"] == container["name"])
        self.assertEqual(rendered, {key: original[key] for key in self.fields})
        self.assertEqual(self.task, original)

    def test_render_rejects_missing_container_or_wrong_family(self):
        for task in (dict(self.task, family="other"), dict(self.task, containerDefinitions=self.task["containerDefinitions"][1:])):
            with self.assertRaises(ValueError):
                render_task_definition(task, self.images, self.fields, "tennis")

    def test_success_requires_the_expected_completed_revision(self):
        require_stable_revision(self.service, "task:5")
        with self.assertRaises(ValueError):
            require_stable_revision(self.service, "task:6")

    def test_rollback_to_healthy_old_revision_is_failure(self):
        rolled_back = copy.deepcopy(self.service)
        rolled_back["taskDefinition"] = "task:4"
        rolled_back["deployments"][0]["taskDefinition"] = "task:4"
        with self.assertRaises(ValueError):
            require_stable_revision(rolled_back, "task:5")

    def test_incomplete_or_scaled_to_zero_service_is_not_success(self):
        for changes in ({"pendingCount": 1}, {"runningCount": 1}, {"desiredCount": 0, "runningCount": 0}):
            with self.assertRaises(ValueError):
                require_stable_revision(dict(self.service, **changes), "task:5")
        for state in ("FAILED", "IN_PROGRESS"):
            service = copy.deepcopy(self.service)
            service["deployments"][0]["rolloutState"] = state
            with self.assertRaises(ValueError):
                require_stable_revision(service, "task:5")

    def test_verification_rejects_unexpected_image(self):
        with self.assertRaises(ValueError):
            check_task_images(self.task, self.images)

    def test_digest_validation_rejects_tags_and_other_repositories(self):
        env = {
            "AWS_REGION": "ap-northeast-1", "ECR_PHP_REPOSITORY": "tennis-php",
            "ECR_NGINX_REPOSITORY": "tennis-nginx", "ECS_PHP_CONTAINER": "php-fpm", "ECS_NGINX_CONTAINER": "nginx",
            "PHP_IMAGE": "610646412385.dkr.ecr.ap-northeast-1.amazonaws.com/tennis-php@sha256:" + "a" * 64,
            "NGINX_IMAGE": "610646412385.dkr.ecr.ap-northeast-1.amazonaws.com/tennis-nginx@sha256:" + "b" * 64,
        }
        with patch.dict(os.environ, env):
            self.assertEqual(set(expected_images()), {"php-fpm", "nginx"})
        for image in ("tennis-php:latest", env["PHP_IMAGE"].replace("/tennis-php@", "/other@")):
            with patch.dict(os.environ, dict(env, PHP_IMAGE=image)), self.assertRaises(ValueError):
                expected_images()


if __name__ == "__main__":
    unittest.main()
