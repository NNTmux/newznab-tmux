<div class="card card-default shadow-sm mb-4">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><i class="fa fa-tv me-2 text-primary"></i>Add Show to Watchlist</h3>
            <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 py-0">
                        <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{url("/myshows")}}">My Shows</a></li>
                        <li class="breadcrumb-item active">{$type|ucwords} Show</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="mb-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <img class="rounded shadow-sm" style="max-width:100px"
                     src="{{url("/covers/tvshows/{$video}_thumb.jpg")}}"
                     onerror="this.src='{{url("/covers/tvshows/no-cover.jpg")}}'"
                     alt="{$show.title|escape:"htmlall"}" />

                <div>
                    <h4 class="mb-1">{$type|ucwords} "{$show.title|escape:"htmlall"}" to watchlist</h4>
                    <p class="text-muted mb-0">Select categories below to organize this show in your collection.</p>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>
                Adding shows to your watchlist will notify you through your
                <a href="{{url("/rss/myshows?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}" class="alert-link">
                    <i class="fa fa-rss me-1"></i>RSS Feed
                </a>
                when new episodes become available.
            </div>
        </div>

        {{Form::open(['id' => 'myshows', 'class' => 'form', 'url' => "myshows?action=do{$type}"])}}
            <input type="hidden" name="id" value="{$video}"/>
            {if isset($from)}<input type="hidden" name="from" value="{$from}" />{/if}

            <div class="mb-4">
                <label class="form-label fw-bold mb-3" for="category">Choose Categories:</label>
                <div class="d-flex flex-wrap gap-3" id="category">
                    {html_checkboxes id="category" name='category' values=$cat_ids output=$cat_names selected=$cat_selected separator=''}
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" name="{$type}">
                    <i class="fa {if $type == 'add'}fa-plus{else}fa-edit{/if} me-2"></i>{$type|ucwords} Show
                </button>
                <a href="{{url("/myshows")}}" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left me-2"></i>Back to My Shows
                </a>
            </div>
        {{Form::close()}}
    </div>
</div>

<style>
/* Style for checkboxes to make them more modern */
#category {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

#category label {
    display: flex;
    align-items: center;
    padding: 8px 15px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

#category label:hover {
    background-color: #e9ecef;
}

#category input[type="checkbox"] {
    margin-right: 8px;
}

#category input[type="checkbox"]:checked + label {
    background-color: #e0f7fa;
    border-color: #4dabf7;
}
</style>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Make checkboxes more interactive
    const checkboxes = document.querySelectorAll('#category input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        const label = document.createElement('label');
        label.setAttribute('for', checkbox.id);
        label.innerHTML = checkbox.nextSibling.textContent;
        checkbox.nextSibling.remove();
        checkbox.parentNode.insertBefore(label, checkbox.nextSibling);

        // Wrap each checkbox in a div for better styling
        const wrapper = document.createElement('div');
        wrapper.className = 'category-checkbox';
        checkbox.parentNode.insertBefore(wrapper, checkbox);
        wrapper.appendChild(checkbox);
        wrapper.appendChild(label);
    });
});
{/literal}
</script>
