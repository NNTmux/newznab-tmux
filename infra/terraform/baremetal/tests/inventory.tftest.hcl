variables {
  servers = {
    indexer = {
      address     = "192.0.2.10"
      ssh_user    = "administrator"
      hostname    = "indexer.example.com"
      admin_cidrs = ["198.51.100.0/24"]
    }
  }
}
run "inventory" {
  command = plan
  assert {
    condition     = output.ansible_inventory.all.children.nntmux.hosts.indexer.ansible_host == "192.0.2.10" && output.ansible_inventory.all.children.nntmux.hosts.indexer.ansible_port == 22
    error_message = "Connection metadata must reach Ansible, including the default SSH port."
  }
  assert {
    condition     = length(keys(output.ansible_inventory.all.children.nntmux.hosts.indexer)) == 5
    error_message = "Inventory must contain only the five public connection/configuration fields."
  }
}
run "invalid_port" {
  command = plan
  variables {
    servers = { indexer = { address = "192.0.2.10", ssh_user = "administrator", hostname = "indexer.example.com", ssh_port = 0, admin_cidrs = ["198.51.100.0/24"] } }
  }
  expect_failures = [var.servers]
}
run "worldwide_ssh" {
  command = plan
  variables {
    servers = { indexer = { address = "192.0.2.10", ssh_user = "administrator", hostname = "indexer.example.com", admin_cidrs = ["0.0.0.0/0"] } }
  }
  expect_failures = [var.servers]
}
run "invalid_address" {
  command = plan
  variables {
    servers = { indexer = { address = "shell;command", ssh_user = "administrator", hostname = "indexer.example.com", admin_cidrs = ["198.51.100.0/24"] } }
  }
  expect_failures = [var.servers]
}

run "reserved_inventory_name" {
  command = plan
  variables {
    servers = { all = { address = "192.0.2.10", ssh_user = "administrator", hostname = "indexer.example.com", admin_cidrs = ["198.51.100.0/24"] } }
  }
  expect_failures = [var.servers]
}
run "ipv6_inventory" {
  command = plan
  variables {
    servers = { indexer = { address = "2001:db8::10", ssh_user = "administrator", hostname = "indexer.example.com", ssh_port = 2222, admin_cidrs = ["2001:db8:1::/64"] } }
  }
  assert {
    condition     = output.ansible_inventory.all.children.nntmux.hosts.indexer.ansible_host == "2001:db8::10" && output.ansible_inventory.all.children.nntmux.hosts.indexer.ansible_port == 2222
    error_message = "IPv6 and custom SSH ports must survive inventory generation."
  }
}
