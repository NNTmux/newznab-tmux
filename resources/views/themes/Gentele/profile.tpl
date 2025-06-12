<div style="position: static;">
<div class="container-fluid px-4 py-3">
                                  <!-- Breadcrumb -->
                                  <nav aria-label="breadcrumb" class="mb-3">
                                      <ol class="breadcrumb">
                                          <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                                          <li class="breadcrumb-item"><a href="#">Profile</a></li>
                                          <li class="breadcrumb-item active">{$user.username|escape:"htmlall"}</li>
                                      </ol>
                                  </nav>

                                  <div class="row">
                                      <div class="col-md-12">
                                          <div class="card shadow-sm mb-4">
                                              <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                  <h5 class="mb-0"><i class="fa fa-user me-2"></i>User Profile</h5>
                                                  <div>
                                                      {if (isset($isadmin) && $isadmin === "true") || !$publicview}
                                                          <a class="btn btn-sm btn-success" href="{{route("profileedit")}}">
                                                              <i class="fa fa-edit me-1"></i>Edit Profile
                                                          </a>
                                                      {/if}
                                                      {if $isadmin === "false" && !$publicview}
                                                          <a class="btn btn-sm btn-danger confirm_action" href="{{url("profile_delete?id={$user.id}")}}">
                                                              <i class="fa fa-trash me-1"></i>Delete Account
                                                          </a>
                                                      {/if}
                                                  </div>
                                              </div>
                                              <div class="card-body">
                                                  <div class="row">
                                                      <!-- Left column -->
                                                      <div class="col-lg-3 mb-4 mb-lg-0">
                                                              <div class="profile-image-container position-relative mx-auto mb-3" style="width: 120px; height: 120px;">
                                                                  <!-- Gravatar image -->
                                                                  <img src="{{Gravatar::get($user.email, ['size' => 120, 'default' => 'mp'])}}"
                                                                       alt="{{$user.username}}"
                                                                       class="img-circle profile-img w-100 h-100"
                                                                       style="background-color: white;">
                                                              </div>

                                                          <div class="list-group mb-4">
                                                              <a href="#general" class="list-group-item list-group-item-action active">
                                                                  <i class="fa fa-info-circle me-2"></i>General Information
                                                              </a>
                                                              <a href="#preferences" class="list-group-item list-group-item-action">
                                                                  <i class="fa fa-sliders me-2"></i>UI Preferences
                                                              </a>
                                                              <a href="#api" class="list-group-item list-group-item-action">
                                                                  <i class="fa fa-key me-2"></i>API & Downloads
                                                              </a>
                                                              {if ($user.id === $userdata.id || $isadmin === "true") && $site->registerstatus == 1}
                                                                  <a href="#invites" class="list-group-item list-group-item-action">
                                                                      <i class="fa fa-envelope me-2"></i>Invites
                                                                  </a>
                                                              {/if}
                                                              {if (isset($isadmin) && $isadmin === "true") && $downloadlist|@count > 0}
                                                                  <a href="#downloads" class="list-group-item list-group-item-action">
                                                                      <i class="fa fa-download me-2"></i>Recent Downloads
                                                                  </a>
                                                              {/if}
                                                          </div>
                                                      </div>

                                                      <!-- Right column -->
                                                      <div class="col-lg-9">
                                                          <!-- General Information -->
                                                          <div class="card mb-4" id="general">
                                                              <div class="card-header bg-light d-flex align-items-center">
                                                                  <i class="fa fa-info-circle me-2"></i>
                                                                  <h6 class="mb-0">General Information</h6>
                                                              </div>
                                                              <div class="card-body">
                                                                  <div class="row mb-3">
                                                                      <div class="col-md-4 text-muted">Username</div>
                                                                      <div class="col-md-8 fw-medium">{$user.username|escape:"htmlall"}</div>
                                                                  </div>

                                                                  {if (isset($isadmin) && $isadmin === "true") || !$publicview}
                                                                      <div class="row mb-3">
                                                                          <div class="col-md-4 text-muted">Email</div>
                                                                          <div class="col-md-8 fw-medium">{$user.email}</div>
                                                                      </div>
                                                                  {/if}

                                                                  <div class="row mb-3">
                                                                      <div class="col-md-4 text-muted">Registered</div>
                                                                      <div class="col-md-8">
                                                                          <div class="d-flex align-items-center">
                                                                              <i class="fa fa-calendar text-muted me-2"></i>
                                                                              {$user.created_at|date_format}
                                                                              <span class="badge bg-light text-dark ms-2">({$user.created_at|timeago} ago)</span>
                                                                          </div>
                                                                      </div>
                                                                  </div>

                                                                    <div class="row mb-3">
                                                                            <div class="col-md-4 text-muted">Role</div>
                                                                            <div class="col-md-8">
                                                                                <div class="d-flex align-items-center flex-wrap">
                                                                                    <i class="fa fa-id-badge text-muted me-2"></i>
                                                                                    <span class="badge bg-primary rounded-pill">{$user.role.name}</span>

                                                                                    {if isset($user.rolechangedate) && $user.rolechangedate != "0000-00-00 00:00:00" && $user.rolechangedate != ""}
                                                                                        <div class="ms-2 d-flex align-items-center">
                                                                                            <i class="fa fa-clock-o text-muted me-1"></i>
                                                                                            <small>Expires: {$user.rolechangedate|date_format}</small>
                                                                                        </div>
                                                                                    {/if}
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                  <div class="row mb-3">
                                                                      <div class="col-md-4 text-muted">Last Login</div>
                                                                      <div class="col-md-8">
                                                                          <div class="d-flex align-items-center">
                                                                              <i class="fa fa-clock-o text-muted me-2"></i>
                                                                              {$user.lastlogin|date_format}
                                                                              <span class="badge bg-light text-dark ms-2">({$user.lastlogin|timeago} ago)</span>
                                                                          </div>
                                                                      </div>
                                                                  </div>

                                                                  {if $userinvitedby && $userinvitedby.username != ""}
                                                                      <div class="row">
                                                                          <div class="col-md-4 text-muted">Invited By</div>
                                                                          <div class="col-md-8">
                                                                              {if $privileged || !$privateprofiles}
                                                                                  <a href="{{url("/profile?name={$userinvitedby.username}")}}" class="text-decoration-none">
                                                                                      <i class="fa fa-user text-muted me-2"></i>{$userinvitedby.username}
                                                                                  </a>
                                                                              {else}
                                                                                  <i class="fa fa-user text-muted me-2"></i>{$userinvitedby.username}
                                                                              {/if}
                                                                          </div>
                                                                      </div>
                                                                  {/if}
                                                              </div>
                                                          </div>

                                                          <!-- UI Preferences -->
                                                          <div class="card mb-4" id="preferences">
                                                              <div class="card-header bg-light d-flex align-items-center">
                                                                  <i class="fa fa-sliders me-2"></i>
                                                                  <h6 class="mb-0">UI Preferences</h6>
                                                              </div>
                                                              <div class="card-body">
                                                                  <div class="row mb-3">
                                                                      <div class="col-md-4 text-muted">Theme</div>
                                                                      <div class="col-md-8">
                                                                          <span class="badge bg-primary">{$user.style}</span>
                                                                      </div>
                                                                  </div>

                                                                  <div class="row">
                                                                      <div class="col-md-4 text-muted">Cover Preferences</div>
                                                                      <div class="col-md-8">
                                                                          <div class="d-flex flex-wrap gap-2">
                                                                              <span class="badge {if $user.movieview == "1"}bg-success{else}bg-secondary{/if}">
                                                                                  <i class="fa fa-film me-1"></i>
                                                                                  {if $user.movieview == "1"}Movie Covers{else}Standard Movie View{/if}
                                                                              </span>

                                                                              <span class="badge {if $user.musicview == "1"}bg-success{else}bg-secondary{/if}">
                                                                                  <i class="fa fa-music me-1"></i>
                                                                                  {if $user.musicview == "1"}Music Covers{else}Standard Music View{/if}
                                                                              </span>

                                                                              <span class="badge {if $user.consoleview == "1"}bg-success{else}bg-secondary{/if}">
                                                                                  <i class="fa fa-gamepad me-1"></i>
                                                                                  {if $user.consoleview == "1"}Console Covers{else}Standard Console View{/if}
                                                                              </span>

                                                                              <span class="badge {if $user.gameview == "1"}bg-success{else}bg-secondary{/if}">
                                                                                  <i class="fa fa-gamepad me-1"></i>
                                                                                  {if $user.gameview == "1"}Game Covers{else}Standard Game View{/if}
                                                                              </span>

                                                                              <span class="badge {if $user.bookview == "1"}bg-success{else}bg-secondary{/if}">
                                                                                  <i class="fa fa-book me-1"></i>
                                                                                  {if $user.bookview == "1"}Book Covers{else}Standard Book View{/if}
                                                                              </span>

                                                                              <span class="badge {if $user.xxxview == "1"}bg-success{else}bg-secondary{/if}">
                                                                                  <i class="fa fa-eye me-1"></i>
                                                                                  {if $user.xxxview == "1"}XXX Covers{else}Standard XXX View{/if}
                                                                              </span>
                                                                          </div>
                                                                      </div>
                                                                  </div>
                                                              </div>
                                                          </div>

                                                          <!-- API & Downloads -->
                                                          <div class="card mb-4" id="api">
                                                              <div class="card-header bg-light d-flex align-items-center">
                                                                  <i class="fa fa-key me-2"></i>
                                                                  <h6 class="mb-0">API & Downloads</h6>
                                                              </div>
                                                              <div class="card-body">
                                                                  <div class="row mb-3">
                                                                      <div class="col-md-5 text-muted">API Hits (Last 24 Hours)</div>
                                                                      <div class="col-md-7">
                                                                          <div class="d-flex align-items-center">
                                                                              <i class="fa fa-server text-muted me-2"></i>
                                                                              <span class="badge bg-primary rounded-pill">{$apirequests}</span>
                                                                          </div>
                                                                      </div>
                                                                  </div>

                                                                  <div class="row mb-3">
                                                                      <div class="col-md-5 text-muted">Downloads (Last 24 Hours)</div>
                                                                      <div class="col-md-7">
                                                                          <div class="d-flex align-items-center">
                                                                              <i class="fa fa-cloud-download text-muted me-2"></i>
                                                                              <div class="progress flex-grow-1" style="height: 20px;">
                                                                                  <div class="progress-bar {if $grabstoday >= $user->role->downloadrequests}bg-danger{else}bg-success{/if}"
                                                                                       role="progressbar"
                                                                                       style="width: {min(($grabstoday / $user->role->downloadrequests) * 100, 100)}%;"
                                                                                       aria-valuenow="{$grabstoday}"
                                                                                       aria-valuemin="0"
                                                                                       aria-valuemax="{$user->role->downloadrequests}">
                                                                                      {$grabstoday} / {$user->role->downloadrequests}
                                                                                  </div>
                                                                              </div>
                                                                          </div>
                                                                      </div>
                                                                  </div>

                                                                  <div class="row mb-3">
                                                                      <div class="col-md-5 text-muted">Total Downloads</div>
                                                                      <div class="col-md-7">
                                                                          <div class="d-flex align-items-center">
                                                                              <i class="fa fa-download text-muted me-2"></i>
                                                                              <span class="badge bg-secondary rounded-pill">{$user.grabs}</span>
                                                                          </div>
                                                                      </div>
                                                                  </div>

                                                                  {if (isset($isadmin) && $isadmin === "true") || !$publicview}
                                                                      <div class="row mb-3">
                                                                          <div class="col-md-5 text-muted">API/RSS Key</div>
                                                                          <div class="col-md-7">
                                                                              <div class="input-group">
                                                                                  <input type="text" class="form-control form-control-sm" value="{$user.api_token}" readonly>
                                                                                  <a href="{{url("/rss/full-feed?dl=1&amp;i={$user.id}&amp;api_token={$user.api_token}")}}" class="btn btn-sm btn-outline-secondary">
                                                                                      <i class="fa fa-rss me-1"></i>RSS
                                                                                  </a>
                                                                                  <a href="{{url("profileedit?action=newapikey")}}" class="btn btn-sm btn-danger">
                                                                                      <i class="fa fa-refresh me-1"></i>Generate New
                                                                                  </a>
                                                                              </div>
                                                                          </div>
                                                                      </div>

                                                                      {if $user.notes|count_characters > 0 || $isadmin === "true"}
                                                                          <div class="row">
                                                                              <div class="col-md-5 text-muted">Admin Notes</div>
                                                                              <div class="col-md-7">
                                                                                  {if $user.notes|count_characters > 0}
                                                                                      <div class="alert alert-info mb-2 p-2">
                                                                                          <i class="fa fa-sticky-note me-2"></i>{$user.notes|escape:htmlall}
                                                                                      </div>
                                                                                  {/if}
                                                                                  {if $isadmin === "true"}
                                                                                      <a href="{{url("/admin/user-edit.php?id={$user.id}#notes")}}" class="btn btn-sm btn-outline-info">
                                                                                          <i class="fa fa-edit me-1"></i>Add/Edit Notes
                                                                                      </a>
                                                                                  {/if}
                                                                              </div>
                                                                          </div>
                                                                      {/if}
                                                                  {/if}
                                                              </div>
                                                          </div>

                                                          <!-- Invites Section -->
                                                          {if ($user.id === $userdata.id || $isadmin === "true") && $site->registerstatus == 1}
                                                              <div class="card mb-4" id="invites">
                                                                  <div class="card-header bg-light d-flex align-items-center">
                                                                      <i class="fa fa-envelope me-2"></i>
                                                                      <h6 class="mb-0">Invites</h6>
                                                                  </div>
                                                                  <div class="card-body">
                                                                      <div class="row mb-3">
                                                                          <div class="col-md-4 text-muted">Available Invites</div>
                                                                          <div class="col-md-8">
                                                                              <div class="d-flex align-items-center">
                                                                                  <span class="badge bg-primary rounded-pill me-3">{$user.invites}</span>

                                                                                  {if $user.invites > 0}
                                                                                      <button id="lnkSendInvite" class="btn btn-sm btn-outline-success" onclick="return false;">
                                                                                          <i class="fa fa-paper-plane me-1"></i>Send Invite
                                                                                      </button>
                                                                                  {/if}
                                                                              </div>

                                                                              {if $user.invites > 0}
                                                                                  <div class="mt-3">
                                                                                      <span class="invitesuccess text-success" id="divInviteSuccess"></span>
                                                                                      <span class="invitefailed text-danger" id="divInviteError"></span>

                                                                                      <div style="display:none;" id="divInvite" class="mt-2">
                                                                                          {{Form::open(['id' => 'frmSendInvite', 'method' => 'get', 'class' => 'row g-2'])}}
                                                                                              <div class="col-md-8">
                                                                                                  <div class="input-group">
                                                                                                      <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                                                                                      {{Form::text('txtInvite', null, ['id' => 'txtInvite', 'class' => 'form-control', 'placeholder' => 'Email address'])}}
                                                                                                  </div>
                                                                                              </div>
                                                                                              <div class="col-md-4">
                                                                                                  {{Form::submit('Send Invite', ['class' => 'btn btn-primary w-100'])}}
                                                                                              </div>
                                                                                          {{Form::close()}}
                                                                                      </div>
                                                                                  </div>
                                                                              {/if}
                                                                          </div>
                                                                      </div>
                                                                  </div>
                                                              </div>
                                                          {/if}

                                                          <!-- Downloads Section -->
                                                          {if (isset($isadmin) && $isadmin === "true") && $downloadlist|@count > 0}
                                                              <div class="card mb-4" id="downloads">
                                                                  <div class="card-header bg-light d-flex align-items-center justify-content-between">
                                                                      <div>
                                                                          <i class="fa fa-download me-2"></i>
                                                                          <h6 class="d-inline mb-0">Recent Downloads</h6>
                                                                      </div>
                                                                      {if $downloadlist|@count > 10}
                                                                          <button class="btn btn-sm btn-outline-secondary" id="toggleDownloads">
                                                                              <i class="fa fa-eye me-1"></i>Show All
                                                                          </button>
                                                                      {/if}
                                                                  </div>
                                                                  <div class="card-body p-0">
                                                                      <div class="table-responsive">
                                                                          <table class="table table-hover mb-0">
                                                                              <thead class="table-light">
                                                                                  <tr>
                                                                                      <th><i class="fa fa-calendar me-1"></i>Date</th>
                                                                                      <th><i class="fa fa-file-archive-o me-1"></i>Release</th>
                                                                                  </tr>
                                                                              </thead>
                                                                              <tbody>
                                                                                  {foreach $downloadlist as $download}
                                                                                      <tr class="{if $download@iteration > 10}extra-download d-none{/if}">
                                                                                          <td width="180" class="align-middle">
                                                                                              <div class="d-flex align-items-center" title="{$download.timestamp}">
                                                                                                  <i class="fa fa-clock-o text-muted me-2"></i>
                                                                                                  {$download.timestamp|date_format}
                                                                                              </div>
                                                                                          </td>
                                                                                          <td>
                                                                                              {if $download->release->guid == ""}
                                                                                                  <span class="text-muted">n/a</span>
                                                                                              {else}
                                                                                                  <a href="{{url("/details/{$download->release->guid}")}}" class="text-decoration-none">
                                                                                                      {$download->release->searchname}
                                                                                                  </a>
                                                                                              {/if}
                                                                                          </td>
                                                                                      </tr>
                                                                                  {/foreach}
                                                                              </tbody>
                                                                          </table>
                                                                      </div>
                                                                  </div>
                                                              </div>
                                                          {/if}
                                                      </div>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>

                              <script>
                              {literal}
                                  // Parse URL parameters
                                  function getUrlParameter(name) {
                                      name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                                      var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                                      var results = regex.exec(location.search);
                                      return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
                                  }

                                  document.addEventListener('DOMContentLoaded', function() {
                                      // Force the window to scroll to top immediately
                                      window.scrollTo(0, 0);

                                      // Clear location hash and force any anchors to load at top of page
                                      if (window.location.hash) {
                                          history.replaceState(null, document.title, window.location.pathname + window.location.search);
                                      }

                                      // Direct approach to prevent scrolling: add inline CSS to body
                                      document.body.style.scrollBehavior = "auto";
                                      document.body.style.overflowAnchor = "none";
                                      document.documentElement.style.scrollPaddingTop = "0";

                                      // Add scroll blocking
                                      function preventScroll(e) {
                                          window.scrollTo(0, 0);
                                      }

                                      // Add event listener to block scrolling for half a second
                                      window.addEventListener('scroll', preventScroll);

                                      // Remove the event listener after a delay
                                      setTimeout(function() {
                                          window.removeEventListener('scroll', preventScroll);
                                      }, 500);

                                      // Invites functionality
                                      const sendInviteBtn = document.getElementById('lnkSendInvite');
                                      const inviteDiv = document.getElementById('divInvite');

                                      if (sendInviteBtn) {
                                          sendInviteBtn.addEventListener('click', function() {
                                              if (inviteDiv.style.display === 'none') {
                                                  inviteDiv.style.display = 'block';
                                                  this.innerHTML = '<i class="fa fa-times me-1"></i>Cancel';
                                              } else {
                                                  inviteDiv.style.display = 'none';
                                                  this.innerHTML = '<i class="fa fa-paper-plane me-1"></i>Send Invite';
                                              }
                                          });
                                      }

                                      // Downloads toggle
                                      const toggleDownloadsBtn = document.getElementById('toggleDownloads');

                                      if (toggleDownloadsBtn) {
                                          toggleDownloadsBtn.addEventListener('click', function() {
                                              const extraRows = document.querySelectorAll('.extra-download');
                                              extraRows.forEach(row => row.classList.toggle('d-none'));

                                              if (this.innerHTML.includes('Show All')) {
                                                  this.innerHTML = '<i class="fa fa-eye-slash me-1"></i>Show Less';
                                              } else {
                                                  this.innerHTML = '<i class="fa fa-eye me-1"></i>Show All';
                                              }
                                          });
                                      }

                                      // Smooth scroll for sidebar navigation
                                      document.querySelectorAll('.list-group-item').forEach(link => {
                                          link.addEventListener('click', function(e) {
                                              // Get target section ID from href attribute
                                              const targetId = this.getAttribute('href');

                                              // Only if this is an anchor link to a section on this page
                                              if (targetId && targetId.startsWith('#')) {
                                                  e.preventDefault(); // Prevent default only for same-page links

                                                  // Remove active class from all links
                                                  document.querySelectorAll('.list-group-item').forEach(item => {
                                                      item.classList.remove('active');
                                                  });

                                                  // Add active class to clicked link
                                                  this.classList.add('active');

                                                  // Get the target element
                                                  const targetElement = document.querySelector(targetId);
                                                  if (targetElement) {
                                                      // Allow smooth scrolling for user-initiated actions
                                                      targetElement.scrollIntoView({ behavior: 'smooth' });
                                                  }
                                              }
                                              // For external links, let the browser handle navigation normally
                                          });
                                      });
                                  });

                                  // After all page resources have loaded
                                  window.addEventListener('load', function() {
                                      // Force scroll back to top
                                      window.scrollTo(0, 0);

                                      // Set focus to body to prevent auto-focus on elements down the page
                                      document.body.focus();
                                  });
                              {/literal}
                              </script>
<style>
    {literal}
    html, body {
        scroll-behavior: auto !important;
        scroll-padding-top: 0 !important;
    }
    body {
        overflow-anchor: none !important;
    }
    .profile-image-container {
        position: relative;
        overflow: hidden;
        border-radius: 50%;
    }

    .profile-img {
        object-fit: cover;
        border-radius: 50% !important;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .profile-initial {
        background: linear-gradient(135deg, #4e54c8, #8f94fb);
        color: white;
        font-weight: bold;
        position: absolute;
        top: 0;
        left: 0;
        z-index: 1;
    }
    {/literal}
</style>
</div>
