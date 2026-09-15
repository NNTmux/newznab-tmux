mock_provider "aws" {
  mock_data "aws_caller_identity" {
    defaults = { account_id = "123456789012" }
  }
  mock_data "aws_ami" {
    defaults = { id = "ami-0123456789abcdef0" }
  }
  mock_data "aws_route53_zone" {
    defaults = { name = "example.com.", private_zone = false }
  }
}
variables {
  aws_region        = "eu-central-1"
  availability_zone = "eu-central-1a"
  environment       = "test"
  hostname          = "indexer.example.com"
  route53_zone_id   = "Z123456789"
}
run "deployment_contract" {
  # Plan only: persistent storage remains protected against destruction.
  command = plan
  assert {
    condition     = length(aws_vpc_security_group_ingress_rule.web) == 2 && toset([for rule in aws_vpc_security_group_ingress_rule.web : rule.from_port]) == toset([80, 443])
    error_message = "Only HTTP and HTTPS may be publicly reachable."
  }
  assert {
    condition     = aws_ebs_volume.data.encrypted && aws_ebs_volume.data.size == 250 && aws_instance.application.root_block_device[0].encrypted && aws_instance.application.root_block_device[0].volume_size == 40
    error_message = "Root and persistent data storage must be encrypted and use the selected sizes."
  }
  assert {
    condition     = aws_instance.application.metadata_options[0].http_tokens == "required" && aws_instance.application.metadata_options[0].http_put_response_hop_limit == 1
    error_message = "IMDSv2 must be required and unavailable to application containers."
  }
  assert {
    condition     = aws_volume_attachment.data.device_name == "/dev/sdf" && aws_volume_attachment.data.stop_instance_before_detaching && aws_instance.application.availability_zone == aws_ebs_volume.data.availability_zone
    error_message = "The data volume must be attached to the application in the same availability zone."
  }
  assert {
    condition     = aws_route53_record.application.zone_id == var.route53_zone_id && aws_route53_record.application.name == var.hostname && aws_eip.application.domain == "vpc"
    error_message = "DNS and the static IP must target the deployed instance."
  }
  assert {
    condition     = aws_ecr_repository.application.image_tag_mutability == "IMMUTABLE" && aws_ecr_repository.application.image_scanning_configuration[0].scan_on_push
    error_message = "Published images must be immutable and scanned."
  }
  assert {
    condition     = local.instance_policy.Statement[0].Action == ["secretsmanager:GetSecretValue"] && local.instance_policy.Statement[6].Condition.StringEquals["ec2:ResourceTag/Deployment"] == local.name
    error_message = "The instance may only read the deployment's application secret."
  }
  assert {
    condition     = alltrue([for statement in local.instance_policy.Statement : !contains(statement.Action, "secretsmanager:PutSecretValue") && !contains(statement.Action, "ecr:PutImage")])
    error_message = "The instance cannot publish images or modify application secrets."
  }
  assert {
    condition     = local.instance_policy.Statement[6].Action == ["ec2:CreateSnapshot"] && endswith(local.instance_policy.Statement[6].Resource, ":volume/*") && local.instance_policy.Statement[7].Action == ["ec2:CreateSnapshot"] && endswith(local.instance_policy.Statement[7].Resource, ":snapshot/*")
    error_message = "Snapshot creation must scope the source volume separately from the tagged destination snapshot."
  }

}
run "invalid_environment" {
  command = plan
  variables { environment = "Invalid Environment" }
  expect_failures = [var.environment]
}
run "invalid_storage" {
  command = plan
  variables { data_volume_size = 10 }
  expect_failures = [var.data_volume_size]
}
run "invalid_zone" {
  command = plan
  variables { availability_zone = "us-east-1a" }
  expect_failures = [var.availability_zone]
}
run "invalid_hostname" {
  command = plan
  variables { hostname = "https://example.com" }
  expect_failures = [var.hostname]
}
