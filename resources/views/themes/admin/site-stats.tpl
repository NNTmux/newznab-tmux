<div class="card shadow-sm mb-4">
			    <div class="card-header bg-light">
			        <h3 class="mb-0">{$title}</h3>
			    </div>
			    <div class="card-body">
			        <!-- Top Grabbers Section -->
			        {if $topgrabs|count > 0}
			            <div class="mb-4">
			                <div class="d-flex align-items-center mb-3">
			                    <i class="fa fa-trophy text-warning me-2"></i>
			                    <h4 class="mb-0">Top Grabbers</h4>
			                </div>
			                <div class="table-responsive">
			                    <table class="table table-striped table-hover">
			                        <thead class="thead-light">
			                            <tr>
			                                <th>
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-user text-muted me-2"></i>
			                                        <span>User</span>
			                                    </div>
			                                </th>
			                                <th>
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-download text-muted me-2"></i>
			                                        <span>Grabs</span>
			                                    </div>
			                                </th>
			                            </tr>
			                        </thead>
			                        <tbody>
			                            {foreach from=$topgrabs item=result}
			                                <tr>
			                                    <td width="75%">
			                                        <a href="{{url("/admin/user-edit?id={$result.id}")}}" class="fw-semibold">
			                                            {$result.username}
			                                        </a>
			                                    </td>
			                                    <td>
			                                        <span class="badge bg-primary rounded-pill">{$result.grabs}</span>
			                                    </td>
			                                </tr>
			                            {/foreach}
			                        </tbody>
			                    </table>
			                </div>
			            </div>
			        {/if}

			        <!-- Signups Section -->
			        <div class="mb-4">
			            <div class="d-flex align-items-center mb-3">
			                <i class="fa fa-user-plus text-success me-2"></i>
			                <h4 class="mb-0">Signups</h4>
			            </div>
			            <div class="table-responsive">
			                <table class="table table-striped table-hover">
			                    <thead class="thead-light">
			                        <tr>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <i class="fa fa-calendar-alt text-muted me-2"></i>
			                                    <span>Month</span>
			                                </div>
			                            </th>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <i class="fa fa-users text-muted me-2"></i>
			                                    <span>Signups</span>
			                                </div>
			                            </th>
			                        </tr>
			                    </thead>
			                    <tbody>
			                        {foreach from=$usersbymonth item=result}
			                            {assign var="totusers" value=$totusers+$result.signups}
			                            <tr>
			                                <td width="75%">{$result.month}</td>
			                                <td>{$result.signups}</td>
			                            </tr>
			                        {/foreach}
			                        <tr class="table-active">
			                            <td class="fw-bold">Total</td>
			                            <td class="fw-bold">{$totusers}</td>
			                        </tr>
			                    </tbody>
			                </table>
			            </div>
			        </div>

			        <!-- Users by Role Section -->
			        <div class="mb-4">
			            <div class="d-flex align-items-center mb-3">
			                <i class="fa fa-user-tag text-info me-2"></i>
			                <h4 class="mb-0">Users by Role</h4>
			            </div>
			            <div class="table-responsive">
			                <table class="table table-striped table-hover">
			                    <thead class="thead-light">
			                        <tr>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <i class="fa fa-id-badge text-muted me-2"></i>
			                                    <span>Role</span>
			                                </div>
			                            </th>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <i class="fa fa-users text-muted me-2"></i>
			                                    <span>Users</span>
			                                </div>
			                            </th>
			                        </tr>
			                    </thead>
			                    <tbody>
			                        {foreach from=$usersbyrole item=result}
			                            {assign var="totrusers" value=$totrusers+$result.users}
			                            <tr>
			                                <td width="75%">
			                                    <span class="badge bg-secondary">{$result.role}</span>
			                                </td>
			                                <td>{$result.users}</td>
			                            </tr>
			                        {/foreach}
			                        <tr class="table-active">
			                            <td class="fw-bold">Total</td>
			                            <td class="fw-bold">{$totrusers}</td>
			                        </tr>
			                    </tbody>
			                </table>
			            </div>
			        </div>

			        <!-- Top Downloads Section -->
			        {if $topdownloads|count > 0}
			            <div class="mb-4">
			                <div class="d-flex align-items-center mb-3">
			                    <i class="fa fa-cloud-download-alt text-primary me-2"></i>
			                    <h4 class="mb-0">Top Downloads</h4>
			                </div>
			                <div class="table-responsive">
			                    <table class="table table-striped table-hover">
			                        <thead class="thead-light">
			                            <tr>
			                                <th>
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-file-archive text-muted me-2"></i>
			                                        <span>Release</span>
			                                    </div>
			                                </th>
			                                <th>
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-download text-muted me-2"></i>
			                                        <span>Grabs</span>
			                                    </div>
			                                </th>
			                                <th>
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-clock text-muted me-2"></i>
			                                        <span>Days Ago</span>
			                                    </div>
			                                </th>
			                            </tr>
			                        </thead>
			                        <tbody>
			                            {foreach from=$topdownloads item=result}
			                                <tr>
			                                    <td width="75%">
			                                        <a href="{{url("/details/{$result.guid}")}}" class="text-decoration-none fw-semibold">
			                                            {$result.searchname|escape:"htmlall"|replace:".":" "}
			                                        </a>
			                                        {if isset($isadmin)}
			                                            <a href="{{url("/admin/release-edit?id={$result.id}")}}" class="ms-2 text-warning">
			                                                <i class="fa fa-edit"></i>
			                                            </a>
			                                        {/if}
			                                    </td>
			                                    <td>
			                                        <span class="badge bg-primary rounded-pill">{$result.grabs}</span>
			                                    </td>
			                                    <td>
			                                        <span title="{$result.adddate}">{$result.adddate|timeago}</span>
			                                    </td>
			                                </tr>
			                            {/foreach}
			                        </tbody>
			                    </table>
			                </div>
			            </div>
			        {/if}

			        <!-- Recent Releases Section -->
			        <div class="mb-3">
			            <div class="d-flex align-items-center mb-3">
			                <i class="fa fa-calendar-check text-success me-2"></i>
			                <h4 class="mb-0">Releases Added In Last 7 Days</h4>
			            </div>
			            <div class="table-responsive">
			                <table class="table table-striped table-hover">
			                    <thead class="thead-light">
			                        <tr>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <i class="fa fa-folder-open text-muted me-2"></i>
			                                    <span>Category</span>
			                                </div>
			                            </th>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <i class="fa fa-file-archive text-muted me-2"></i>
			                                    <span>Releases</span>
			                                </div>
			                            </th>
			                        </tr>
			                    </thead>
			                    <tbody>
			                        {foreach from=$recent item=result}
			                            <tr>
			                                <td>
			                                    <span class="badge bg-secondary rounded-pill">{$result.category} > {$result.category}</span>
			                                </td>
			                                <td>{$result.count}</td>
			                            </tr>
			                        {/foreach}
			                    </tbody>
			                </table>
			            </div>
			        </div>
			    </div>
			    <div class="card-footer bg-light">
			        <div class="text-center">
			            <i class="fas fa-info-circle me-1"></i>
			            <span>Statistics updated: {$smarty.now|date_format:"%Y-%m-%d %H:%M:%S"}</span>
			        </div>
			    </div>
			</div>
