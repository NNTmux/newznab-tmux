<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{$title}</h4>
            <a href="{{url("/admin/collection_regexes-list")}}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i>Back to Regexes List
            </a>
        </div>
    </div>

    <div class="card-body">
        {if isset($error) && $error != ''}
            <div class="alert alert-danger">{$error}</div>
        {/if}

        <form action="{{url("/admin/collection_regexes-edit?action=submit")}}" method="POST" id="regexForm">
            {{csrf_field()}}
            <input type="hidden" name="id" value="{$regex.id}"/>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="group_regex" class="form-label fw-bold">Group:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
                        <input type="text" id="group_regex" name="group_regex" class="form-control" value="{$regex.group_regex|escape:html}"/>
                    </div>
                    <small class="text-muted">
                        Regex to match against a group or multiple groups.<br/>
                        Delimiters are already added, and PCRE_CASELESS is added after for case insensitivity.<br/>
                        An example of matching a single group: alt\.binaries\.example<br/>
                        An example of matching multiple groups: alt\.binaries.*
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="regex" class="form-label fw-bold">Regex:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-code"></i></span>
                        <textarea id="regex" name="regex" class="form-control" rows="6">{$regex.regex|escape:html}</textarea>
                    </div>
                    <small class="text-muted">
                        The regex to use when matching (grouping) collections.<br/>
                        The regex delimiters are not added, you MUST add them. See <a href="http://php.net/manual/en/regexp.reference.delimiters">this</a> page.<br/>
                        To make the regex case insensitive, add i after the last delimiter.<br/>
                        You MUST include at least one regex capture group.<br/>
                        You MUST name your regex capture groups (the ones you want to be included).<br/>
                        A string will be created from your matched capture groups.<br/>
                        The string will form part of a "collection hash".<br/>
                        The collection hash is used to group many parts together, to form the finalized release.<br/>
                        The usenet group and name of the poster are added automatically when hashing.<br/>
                        Capture groups are sorted alphabetically (by capture group name) when concatenating the string.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="description" class="form-label fw-bold">Description:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-align-left"></i></span>
                        <textarea id="description" name="description" class="form-control" rows="4">{$regex.description|escape:html}</textarea>
                    </div>
                    <small class="text-muted">
                        Description for this regex.<br/>
                        You can include an example usenet subject this regex would match on.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="ordinal" class="form-label fw-bold">Ordinal:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-sort-numeric-up"></i></span>
                        <input class="form-control" id="ordinal" name="ordinal" type="number" value="{$regex.ordinal}"/>
                    </div>
                    <small class="text-muted">
                        The order to run this regex in.<br/>
                        Must be a number, 0 or higher.<br/>
                        If multiple regex have the same ordinal, MySQL will randomly sort them.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Active:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="form-check form-check-inline">
                        {html_radios id="status" name='status' values=$status_ids output=$status_names selected=$regex.status separator='</div><div class="form-check form-check-inline">'}
                    </div>
                    <small class="text-muted">Only active regex are used during the collection matching process.</small>
                </div>
            </div>
        </form>
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between">
            <a href="{{url("/admin/collection_regexes-list")}}" class="btn btn-outline-secondary">
                <i class="fa fa-times me-2"></i>Cancel
            </a>
            <button type="submit" form="regexForm" class="btn btn-success">
                <i class="fa fa-save me-2"></i>Save Regex
            </button>
        </div>
    </div>
</div>

<style>
{literal}
/* Textarea styling */
textarea {
    min-height: 80px;
}

#regex {
    font-family: monospace;
}

/* Form styling improvements */
.form-label {
    margin-bottom: 0.5rem;
}

/* Number input styling */
input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Improve input focus states */
.form-control:focus,
.form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
{/literal}
</style>
