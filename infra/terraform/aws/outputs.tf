output "instance_id" {
  description = "EC2 instance ID for Systems Manager commands."
  value       = aws_instance.application.id
}
output "static_ip" {
  description = "Elastic IP serving the application."
  value       = aws_eip.application.public_ip
}
output "hostname" {
  description = "Application hostname."
  value       = aws_route53_record.application.fqdn
}
output "data_volume_id" {
  description = "Protected persistent data volume ID."
  value       = aws_ebs_volume.data.id
}
output "repository_url" {
  description = "ECR repository for manually published release images."
  value       = aws_ecr_repository.application.repository_url
}
output "secret_arn" {
  description = "Secrets Manager container to populate with dotenv configuration."
  value       = aws_secretsmanager_secret.application.arn
}
output "assets_location" {
  description = "Versioned deployment assets; synchronize these explicitly on existing hosts."
  value       = "s3://${aws_s3_bucket.assets.id}/${local.asset_prefix}/"
}

output "host_config" {
  description = "Public host parameters for the selected deployment interface."
  value = {
    provider   = "aws", region = var.aws_region, deployment = local.name
    hostname   = var.hostname, data_volume_id = aws_ebs_volume.data.id
    repository = aws_ecr_repository.application.repository_url
    secret_arn = aws_secretsmanager_secret.application.arn
  }
}
