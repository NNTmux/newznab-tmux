output "ansible_inventory" {
  description = "Secret-free Ansible inventory for independently installed physical servers."
  value = {
    all = {
      children = {
        nntmux = {
          hosts = {
            for name, server in var.servers : name => {
              ansible_host       = server.address
              ansible_port       = server.ssh_port
              ansible_user       = server.ssh_user
              nntmux_hostname    = server.hostname
              nntmux_admin_cidrs = sort(tolist(server.admin_cidrs))
            }
          }
        }
      }
    }
  }
}
