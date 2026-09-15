locals {
  name            = "nntmux-${var.environment}"
  asset_directory = "${path.module}/../../../deploy/cloud"
  asset_files = toset([
    "compose.yaml", "Caddyfile", "deploy.sh", "volume.sh", "nntmux.service",
    "nntmux-data.service", "nntmux-backup.service", "nntmux-backup.timer", "docker-data.conf", "docker-daemon.json", "providers/aws.sh"
  ])
  asset_prefix = "releases/${sha256(join("", [for name in sort(tolist(local.asset_files)) : filesha256("${local.asset_directory}/${name}")]))}"
}

data "aws_caller_identity" "current" {}
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"]
  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*"]
  }
  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}
data "aws_route53_zone" "application" {
  zone_id      = var.route53_zone_id
  private_zone = false
}

resource "aws_vpc" "application" {
  cidr_block           = var.vpc_cidr
  enable_dns_support   = true
  enable_dns_hostnames = true
  tags                 = { Name = local.name }
}
resource "aws_subnet" "application" {
  vpc_id            = aws_vpc.application.id
  cidr_block        = cidrsubnet(var.vpc_cidr, 8, 0)
  availability_zone = var.availability_zone
  tags              = { Name = local.name }
}
resource "aws_internet_gateway" "application" {
  vpc_id = aws_vpc.application.id
  tags   = { Name = local.name }
}
resource "aws_route_table" "application" {
  vpc_id = aws_vpc.application.id
  tags   = { Name = local.name }
}
resource "aws_route" "internet" {
  route_table_id         = aws_route_table.application.id
  destination_cidr_block = "0.0.0.0/0"
  gateway_id             = aws_internet_gateway.application.id
}
resource "aws_route_table_association" "application" {
  subnet_id      = aws_subnet.application.id
  route_table_id = aws_route_table.application.id
}
resource "aws_security_group" "application" {
  name        = local.name
  description = "Public HTTP and HTTPS; administration uses Systems Manager"
  vpc_id      = aws_vpc.application.id
  tags        = { Name = local.name }
}
resource "aws_vpc_security_group_ingress_rule" "web" {
  for_each          = { http = 80, https = 443 }
  security_group_id = aws_security_group.application.id
  description       = "Public ${each.key}"
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "tcp"
  from_port         = each.value
  to_port           = each.value
}
resource "aws_vpc_security_group_egress_rule" "outbound" {
  security_group_id = aws_security_group.application.id
  description       = "NNTP, external metadata APIs, DNS, and AWS service access"
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
}
resource "aws_eip" "application" {
  domain = "vpc"
  tags   = { Name = local.name }
}
resource "aws_eip_association" "application" {
  instance_id   = aws_instance.application.id
  allocation_id = aws_eip.application.id
}
resource "aws_route53_record" "application" {
  zone_id = var.route53_zone_id
  name    = var.hostname
  type    = "A"
  ttl     = 300
  records = [aws_eip.application.public_ip]
  lifecycle {
    precondition {
      condition     = !data.aws_route53_zone.application.private_zone && (var.hostname == trimsuffix(data.aws_route53_zone.application.name, ".") || endswith(var.hostname, ".${trimsuffix(data.aws_route53_zone.application.name, ".")}"))
      error_message = "hostname must belong to the supplied public hosted zone."
    }
  }
}
resource "aws_ebs_volume" "data" {
  availability_zone = var.availability_zone
  type              = "gp3"
  size              = var.data_volume_size
  encrypted         = true
  tags              = { Name = "${local.name}-data" }
  lifecycle {
    prevent_destroy = true
    ignore_changes  = [snapshot_id]
  }
}
resource "aws_volume_attachment" "data" {
  device_name                    = "/dev/sdf"
  volume_id                      = aws_ebs_volume.data.id
  instance_id                    = aws_instance.application.id
  stop_instance_before_detaching = true
}
resource "aws_ecr_repository" "application" {
  name                 = local.name
  image_tag_mutability = "IMMUTABLE"
  image_scanning_configuration { scan_on_push = true }
  encryption_configuration { encryption_type = "AES256" }
}
resource "aws_secretsmanager_secret" "application" {
  name                    = "${local.name}/application"
  description             = "Operator-populated dotenv configuration; Terraform never manages secret values"
  recovery_window_in_days = 30
}
resource "aws_s3_bucket" "assets" {
  bucket_prefix = "${local.name}-deploy-"
}
resource "aws_s3_bucket_versioning" "assets" {
  bucket = aws_s3_bucket.assets.id
  versioning_configuration { status = "Enabled" }
}
resource "aws_s3_bucket_server_side_encryption_configuration" "assets" {
  bucket = aws_s3_bucket.assets.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}
resource "aws_s3_bucket_public_access_block" "assets" {
  bucket                  = aws_s3_bucket.assets.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}
resource "aws_s3_bucket_policy" "assets" {
  bucket = aws_s3_bucket.assets.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Deny", Principal = "*", Action = "s3:*"
      Resource  = [aws_s3_bucket.assets.arn, "${aws_s3_bucket.assets.arn}/*"]
      Condition = { Bool = { "aws:SecureTransport" = "false" } }
    }]
  })
}
resource "aws_s3_object" "assets" {
  for_each               = local.asset_files
  bucket                 = aws_s3_bucket.assets.id
  key                    = "${local.asset_prefix}/${each.value}"
  source                 = "${local.asset_directory}/${each.value}"
  source_hash            = filesha256("${local.asset_directory}/${each.value}")
  server_side_encryption = "AES256"
}
resource "aws_instance" "application" {
  ami                         = data.aws_ami.ubuntu.id
  instance_type               = var.instance_type
  availability_zone           = var.availability_zone
  subnet_id                   = aws_subnet.application.id
  vpc_security_group_ids      = [aws_security_group.application.id]
  associate_public_ip_address = true
  iam_instance_profile        = aws_iam_instance_profile.application.name
  metadata_options {
    http_endpoint               = "enabled"
    http_tokens                 = "required"
    http_put_response_hop_limit = 1
  }
  root_block_device {
    volume_type = "gp3"
    volume_size = var.root_volume_size
    encrypted   = true
  }
  user_data = templatefile("${path.module}/cloud-init.yaml.tftpl", {
    host_config = base64encode(jsonencode({
      provider       = "aws"
      region         = var.aws_region, deployment = local.name, hostname = var.hostname
      data_volume_id = aws_ebs_volume.data.id, secret_arn = aws_secretsmanager_secret.application.arn
      repository     = aws_ecr_repository.application.repository_url
      assets_bucket  = aws_s3_bucket.assets.id, assets_prefix = local.asset_prefix
    }))
    assets_bucket = aws_s3_bucket.assets.id
    assets_prefix = local.asset_prefix
    region        = var.aws_region
  })
  tags = { Name = local.name }
  lifecycle {
    ignore_changes = [ami, user_data]
  }
  depends_on = [aws_route.internet, aws_route_table_association.application, aws_s3_object.assets, aws_iam_role_policy.application]
}
