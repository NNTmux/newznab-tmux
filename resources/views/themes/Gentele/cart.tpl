<div class="container-fluid px-4 py-3">
	    <!-- Breadcrumb -->
	    <nav aria-label="breadcrumb" class="mb-3">
	        <ol class="breadcrumb">
	            <li class="breadcrumb-item"><a href="{{url({$site->home_link})}}">Home</a></li>
	            <li class="breadcrumb-item active">Download Basket</li>
	        </ol>
	    </nav>

	    <!-- RSS Feed Alert -->
	    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
	        <i class="fa fa-rss-square me-3 fs-4"></i>
	        <div>
	            <strong>RSS Feed</strong> <br/>
	            Your download basket can also be accessed via an <a href="{{url("/rss/cart?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}&amp;del=1")}}" class="alert-link">RSS feed</a>. Some NZB downloaders can read this feed and automatically start downloading.
	        </div>
	    </div>

	    <!-- Main Content -->
	    {if $results|@count > 0}
	        <!-- Cart Items Card -->
	        <div class="card shadow-sm mb-4">
	            <div class="card-header bg-light">
	                <div class="row">
	                    <div class="col-md-6">
	                        <h5 class="mb-0">My Download Basket</h5>
	                    </div>
	                    <div class="col-md-6 d-flex justify-content-end">
	                        {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
	                            {{csrf_field()}}
	                            <div class="nzb_multi_operations d-flex align-items-center">
	                                <small class="me-2">With Selected:</small>
	                                <div class="btn-group">
	                                    <button type="button" class="nzb_multi_operations_download_cart btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
	                                        <i class="fa fa-cloud-download"></i>
	                                    </button>
	                                    <button type="button" class="nzb_multi_operations_cartdelete btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete from cart">
	                                        <i class="fa fa-trash"></i>
	                                    </button>
	                                </div>
	                            </div>
	                        {{Form::close()}}
	                    </div>
	                </div>
	            </div>

	            <div class="card-body p-0">
	                <div class="table-responsive">
	                    <table class="table table-striped table-hover mb-0">
	                        <thead class="thead-light">
	                            <tr>
	                                <th style="width: 30px">
	                                    <input id="check-all" type="checkbox" class="flat-all">
	                                </th>
	                                <th>Name</th>
	                                <th>Added</th>
	                                <th class="text-end">Action</th>
	                            </tr>
	                        </thead>
	                        <tbody>
	                            {foreach $results as $result}
	                                <tr id="guid{$result->release->guid}">
	                                    <td>
	                                        <input id="chk{$result->release->guid|substr:0:7}" type="checkbox" name="table_records" class="flat" value="{$result->release->guid}">
	                                    </td>
	                                    <td>
	                                        <a href="{{url("/details/{$result->release->guid}")}}" class="text-decoration-none fw-semibold">{$result->release->searchname|escape:"htmlall"|wordwrap:75:"\n":true}</a>
	                                    </td>
	                                    <td>
	                                        <div class="d-flex align-items-center">
	                                            <i class="fa fa-clock-o text-muted me-2"></i>
	                                            <span title="{$result.created_at}">{$result.created_at|timeago} ago</span>
	                                        </div>
	                                    </td>
	                                    <td class="text-end">
	                                        <div class="d-flex justify-content-end gap-2">
	                                            <a href="{{url("/getnzb?id={$result->release->guid}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZB">
	                                                <i class="fa fa-cloud-download"></i>
	                                            </a>
	                                            <a href="{{url("/details/{$result->release->guid}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="View details">
	                                                <i class="fa fa-info-circle"></i>
	                                            </a>
	                                            <a href="{{url("/cart/delete/{$result->release->guid}")}}" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete from cart">
	                                                <i class="fa fa-trash"></i>
	                                            </a>
	                                        </div>
	                                    </td>
	                                </tr>
	                            {/foreach}
	                        </tbody>
	                    </table>
	                </div>
	            </div>

	            <div class="card-footer">
	                <div class="row">
	                    <div class="col-md-6">
	                        <span class="text-muted">Found {$results|@count} items in your basket</span>
	                    </div>
	                    <div class="col-md-6 d-flex justify-content-end">
	                        <div class="nzb_multi_operations d-flex align-items-center">
	                            <small class="me-2">With Selected:</small>
	                            <div class="btn-group">
	                                <button type="button" class="nzb_multi_operations_download_cart btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
	                                    <i class="fa fa-cloud-download"></i>
	                                </button>
	                                <button type="button" class="nzb_multi_operations_cartdelete btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete from cart">
	                                    <i class="fa fa-trash"></i>
	                                </button>
	                            </div>
	                        </div>
	                    </div>
	                </div>
	            </div>
	        </div>
	    {else}
	        <div class="alert alert-info">
	            <i class="fa fa-info-circle me-2"></i>
	            There are no NZBs in your download basket.
	        </div>
	    {/if}
	</div>
<script>
    {literal}
    document.addEventListener('DOMContentLoaded', function() {
        // Handle "check all" functionality
        const checkAllBox = document.getElementById('check-all');
        if (checkAllBox) {
            checkAllBox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="table_records"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = checkAllBox.checked;
                });
            });
        }

        // Handle multi-download button
        const downloadButtons = document.querySelectorAll('.nzb_multi_operations_download_cart');
        downloadButtons.forEach(button => {
            button.addEventListener('click', function() {
                const selectedGuids = getSelectedGuids();
                if (selectedGuids.length === 0) {
                    PNotify.error({
                        title: 'No Items Selected',
                        text: 'Please select at least one item to download',
                        icon: 'fa fa-exclamation-circle'
                    });
                    return;
                }

                // Instead of creating multiple iframes, create one download request with all GUIDs
                if (selectedGuids.length === 1) {
                    // For single items, direct download
                    window.location.href = `/getnzb?id=${selectedGuids[0]}`;
                    PNotify.success({
                        title: 'Download Started',
                        text: 'Your NZB file is being downloaded',
                        icon: 'fa fa-cloud-download'
                    });
                } else {
                    // For multiple items, create a single iframe with all GUIDs
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = `/getnzb?id=${selectedGuids.join(',')}&zip=1`;
                    document.body.appendChild(iframe);

                    PNotify.success({
                        title: 'Download Started',
                        text: `${selectedGuids.length} NZB files are being downloaded as a ZIP archive`,
                        icon: 'fa fa-cloud-download'
                    });

                    // Remove iframe after download starts
                    setTimeout(() => {
                        document.body.removeChild(iframe);
                    }, 5000);
                }
            });
        });

        // Handle multi-delete button
        const deleteButtons = document.querySelectorAll('.nzb_multi_operations_cartdelete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const selectedGuids = getSelectedGuids();
                if (selectedGuids.length === 0) {
                    PNotify.error({
                        title: 'No Items Selected',
                        text: 'Please select at least one item to delete',
                        icon: 'fa fa-exclamation-circle'
                    });
                    return;
                }

                PNotify.notice({
                    title: 'Confirm Deletion',
                    text: 'Are you sure you want to remove these items from your cart?',
                    icon: 'fa fa-question-circle',
                    hide: false,
                    modules: {
                        Confirm: {
                            confirm: true,
                            buttons: [{
                                text: 'Yes',
                                primary: true,
                                click: function(notice) {
                                    notice.close();

                                    // Create and submit a form to delete selected items
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.action = '/cart/delete/' + selectedGuids.join(',');

                                    // Add CSRF token
                                    const csrfToken = document.querySelector('input[name="_token"]').value;
                                    const csrfInput = document.createElement('input');
                                    csrfInput.type = 'hidden';
                                    csrfInput.name = '_token';
                                    csrfInput.value = csrfToken;
                                    form.appendChild(csrfInput);

                                    document.body.appendChild(form);
                                    form.submit();

                                    PNotify.success({
                                        title: 'Items Removed',
                                        text: `${selectedGuids.length} item(s) are being removed from your cart`,
                                        icon: 'fa fa-trash',
                                        delay: 3000
                                    });
                                }
                            }, {
                                text: 'No',
                                click: function(notice) {
                                    notice.close();
                                }
                            }]
                        }
                    }
                });
            });
        });

        // Helper function to get selected GUIDs
        function getSelectedGuids() {
            const checkboxes = document.querySelectorAll('input[name="table_records"]:checked');
            return Array.from(checkboxes).map(checkbox => checkbox.value);
        }
    });
    {/literal}
</script>
