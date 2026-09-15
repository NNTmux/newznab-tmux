variable "servers" {
  description = "Existing physical servers. Public connection metadata only; never supply credentials."
  type = map(object({
    address     = string
    ssh_port    = optional(number, 22)
    ssh_user    = string
    hostname    = string
    admin_cidrs = set(string)
  }))
  validation {
    condition = length(var.servers) > 0 && alltrue([
      for name, server in var.servers :
      can(regex("^[a-z][a-z0-9_-]{0,62}$", name)) && !contains(["all", "nntmux", "ungrouped"], name) &&
      !strcontains(server.address, "/") &&
      can(cidrhost("${server.address}/${strcontains(server.address, ":") ? 128 : 32}", 0)) &&
      can(regex("^[a-z_][a-z0-9_-]{0,31}$", server.ssh_user)) &&
      server.ssh_port >= 1 && server.ssh_port <= 65535 && floor(server.ssh_port) == server.ssh_port &&
      length(server.hostname) <= 253 &&
      can(regex("^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$", server.hostname)) &&
      length(server.admin_cidrs) > 0 && alltrue([
        for cidr in server.admin_cidrs : can(cidrhost(cidr, 0)) && !endswith(cidr, "/0")
      ])
    ])
    error_message = "Supply valid inventory names, IP addresses, SSH users/ports, public hostnames, and non-worldwide administrator CIDRs."
  }
  validation {
    condition     = length(distinct([for server in values(var.servers) : server.hostname])) == length(var.servers)
    error_message = "Every server must have a distinct hostname."
  }
}
