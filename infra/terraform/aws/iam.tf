resource "aws_iam_role" "application" {
  name = local.name
  assume_role_policy = jsonencode({
    Version   = "2012-10-17"
    Statement = [{ Effect = "Allow", Principal = { Service = "ec2.amazonaws.com" }, Action = "sts:AssumeRole" }]
  })
}
resource "aws_iam_instance_profile" "application" {
  name = local.name
  role = aws_iam_role.application.name
}
resource "aws_iam_role_policy_attachment" "ssm" {
  role       = aws_iam_role.application.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}
resource "aws_iam_role_policy" "application" {
  name   = "deployment"
  role   = aws_iam_role.application.id
  policy = jsonencode(local.instance_policy)
}

locals {
  instance_policy = {
    Version = "2012-10-17"
    Statement = [
      { Effect = "Allow", Action = ["secretsmanager:GetSecretValue"], Resource = aws_secretsmanager_secret.application.arn },
      { Effect = "Allow", Action = ["s3:GetObject"], Resource = "${aws_s3_bucket.assets.arn}/releases/*" },
      { Effect = "Allow", Action = ["s3:ListBucket"], Resource = aws_s3_bucket.assets.arn, Condition = { StringLike = { "s3:prefix" = ["releases/*"] } } },
      { Effect = "Allow", Action = ["ecr:GetAuthorizationToken"], Resource = "*" },
      { Effect = "Allow", Action = ["ecr:BatchGetImage", "ecr:GetDownloadUrlForLayer", "ecr:BatchCheckLayerAvailability"], Resource = aws_ecr_repository.application.arn },
      { Effect = "Allow", Action = ["ec2:DescribeVolumes", "ec2:DescribeSnapshots", "ec2:DescribeInstances"], Resource = "*" },
      { Effect = "Allow", Action = ["ec2:CreateSnapshot"], Resource = "arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:volume/*", Condition = { StringEquals = { "ec2:ResourceTag/Deployment" = local.name } } },
      { Effect = "Allow", Action = ["ec2:CreateSnapshot"], Resource = "arn:aws:ec2:${var.aws_region}::snapshot/*", Condition = { StringEquals = { "aws:RequestTag/Deployment" = local.name } } },
      { Effect = "Allow", Action = ["ec2:CreateVolume"], Resource = "arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:volume/*", Condition = { StringEquals = { "aws:RequestTag/Deployment" = local.name } } },
      { Effect = "Allow", Action = ["ec2:CreateVolume", "ec2:DeleteSnapshot"], Resource = "arn:aws:ec2:${var.aws_region}::snapshot/*", Condition = { StringEquals = { "ec2:ResourceTag/Deployment" = local.name } } },
      { Effect = "Allow", Action = ["ec2:AttachVolume", "ec2:DetachVolume"], Resource = ["arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:instance/*", "arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:volume/*"], Condition = { StringEquals = { "ec2:ResourceTag/Deployment" = local.name } } },
      { Effect = "Allow", Action = ["ec2:CreateTags"], Resource = ["arn:aws:ec2:${var.aws_region}::snapshot/*", "arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:volume/*"], Condition = { StringEquals = { "ec2:CreateAction" = ["CreateSnapshot", "CreateVolume"] } } }
    ]
  }
}
