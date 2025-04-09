<div class="container">
		    <div class="row justify-content-center mt-5">
		        <div class="col-md-10">
		            <div class="card shadow-sm mb-4">
		                {if $loggedin == "true"}
		                    {if $smarty.server.REQUEST_URI == "/"}
		                        <div class="card-body p-4">
		                            {foreach from=$content item=c}
		                                {$c->body}
		                            {/foreach}
		                        </div>
		                    {else}
		                        {foreach from=$content item=c}
		                            <div class="card-header bg-light">
		                                <h4 class="mb-0">{$c->title}</h4>
		                                <div class="breadcrumb-wrapper mt-2">
		                                    <nav aria-label="breadcrumb">
		                                        <ol class="breadcrumb mb-0 bg-transparent p-0">
		                                            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
		                                            <li class="breadcrumb-item active">{$c->title}</li>
		                                        </ol>
		                                    </nav>
		                                </div>
		                            </div>
		                            <div class="card-body p-4">
		                                {$c->body}
		                            </div>
		                        {/foreach}
		                    {/if}
		                {else}
		                    {foreach from=$content item=c}
		                        {if $c->role == 0}
		                            <div class="card-header bg-light">
		                                <h4 class="mb-0">{$c->title}</h4>
		                                <div class="breadcrumb-wrapper mt-2">
		                                    <nav aria-label="breadcrumb">
		                                        <ol class="breadcrumb mb-0 bg-transparent p-0">
		                                            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
		                                            <li class="breadcrumb-item active">{$c->title}</li>
		                                        </ol>
		                                    </nav>
		                                </div>
		                            </div>
		                            <div class="card-body p-4">
		                                {$c->body}
		                            </div>
		                        {/if}
		                    {/foreach}
		                {/if}

		                <div class="card-footer bg-light">
		                    <div class="text-center">
		                        <i class="fas fa-info-circle me-1"></i>
		                        <span>Content last updated: {$smarty.now|date_format:"%Y-%m-%d"}</span>
		                    </div>
		                </div>
		            </div>
		        </div>
		    </div>
		</div>
