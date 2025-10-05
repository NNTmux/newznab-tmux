<div class="card">
		    <div class="card-header">
		        <div class="d-flex justify-content-between align-items-center">
		            <h4 class="mb-0">{$title}</h4>
		        </div>
		    </div>

		    <div class="card-body">
		        <form action="site-edit?action=submit" method="post" id="siteSettingsForm">
    {{csrf_field()}}
    <style>
        /* Select color states */
        #siteSettingsForm select.select-yes {
            background-color: #d1e7dd !important; /* Bootstrap success bg */
            border-color: #badbcc !important;
            color: #0f5132 !important;
        }
        #siteSettingsForm select.select-no {
            background-color: #f8d7da !important; /* Bootstrap danger bg */
            border-color: #f5c2c7 !important;
            color: #842029 !important;
        }
        #siteSettingsForm select.select-other {
            background-color: #e2e3e5 !important; /* Bootstrap secondary bg */
            border-color: #d3d6d8 !important;
            color: #41464b !important;
        }
        /* Keep focus outline accessible */
        #siteSettingsForm select:focus { box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); }
    </style>
    <script>
        (function(){
            function applySelectColor(sel){
                if(!sel || !sel.options || sel.selectedIndex < 0) return;
                const txt = sel.options[sel.selectedIndex].text.trim().toLowerCase();
                sel.classList.remove('select-yes','select-no','select-other');
                if(['yes','enabled','on','true'].includes(txt)) sel.classList.add('select-yes');
                else if(['no','disabled','off','false'].includes(txt)) sel.classList.add('select-no');
                else sel.classList.add('select-other');
            }
            function init(){
                document.querySelectorAll('#siteSettingsForm select').forEach(function(sel){
                    applySelectColor(sel);
                    sel.addEventListener('change', function(){ applySelectColor(sel); });
                });
            }
            if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
        })();
    </script>

		            {if isset ($error) && $error != ''}
		                <div class="alert alert-danger mb-4">{$error}</div>
		            {/if}

		            <div class="mb-4">
		                <h5 class="border-bottom pb-2 mb-3">Main Site Settings, HTML Layout, Tags</h5>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="strapline" class="form-label fw-bold">Strapline:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-quote-right"></i></span>
		                        <input id="strapline" class="form-control" name="strapline" type="text" value="{$site->strapline}"/>
		                    </div>
		                    <small class="text-muted">Displayed in the header on every public page.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="metatitle" class="form-label fw-bold">Meta Title:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-heading"></i></span>
		                        <input id="metatitle" class="form-control" name="metatitle" type="text" value="{$site->metatitle}"/>
		                    </div>
		                    <small class="text-muted">Stem meta-tag appended to all page title tags.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="metadescription" class="form-label fw-bold">Meta Description:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-comment"></i></span>
		                        <textarea id="metadescription" class="form-control" name="metadescription" rows="3">{$site->metadescription}</textarea>
		                    </div>
		                    <small class="text-muted">Stem meta-description appended to all page meta description tags.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="metakeywords" class="form-label fw-bold">Meta Keywords:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-tags"></i></span>
		                        <textarea id="metakeywords" class="form-control" name="metakeywords" rows="3">{$site->metakeywords}</textarea>
		                    </div>
		                    <small class="text-muted">Stem meta-keywords appended to all page meta keyword tags.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="footer" class="form-label fw-bold">Footer:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-copyright"></i></span>
		                        <textarea id="footer" class="form-control" name="footer" rows="3">{$site->footer}</textarea>
		                    </div>
		                    <small class="text-muted">Displayed in the footer section of every public page.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="home_link" class="form-label fw-bold">Default Home Page:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-home"></i></span>
		                        <input id="home_link" class="form-control" name="home_link" type="text" value="{$site->home_link}"/>
		                    </div>
		                    <small class="text-muted">The relative path to the landing page shown when a user logs in, or clicks the home link.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="dereferrer_link" class="form-label fw-bold">Dereferrer Link:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-external-link-alt"></i></span>
		                        <input id="dereferrer_link" class="form-control" name="dereferrer_link" type="text" value="{$site->dereferrer_link}"/>
		                    </div>
		                    <small class="text-muted">Optional URL to prepend to external links.</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="tandc" class="form-label fw-bold">Terms and Conditions:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-gavel"></i></span>
		                        <textarea id="tandc" class="form-control" name="tandc" rows="5">{$site->tandc}</textarea>
		                    </div>
		                    <small class="text-muted">Text displayed in the terms and conditions page.</small>
		                </div>
		            </div>

		<div class="mb-4">
				    <h5 class="border-bottom pb-2 mb-3">Usenet Settings</h5>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="nzbsplitlevel" class="form-label fw-bold">Nzb File Path Level Deep:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-folder-tree"></i></span>
				            <input id="nzbsplitlevel" class="form-control" name="nzbsplitlevel" type="text" value="{$site->nzbsplitlevel}"/>
				        </div>
				        <small class="text-muted">
				            Levels deep to store the nzb Files.
				            <br/><strong>If you change this you must run the misc/testing/DB/nzb-reorg script!</strong>
				        </small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="partretentionhours" class="form-label fw-bold">Part Retention Hours:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-clock"></i></span>
				            <input id="partretentionhours" class="form-control" name="partretentionhours" type="text" value="{$site->partretentionhours}"/>
				        </div>
				        <small class="text-muted">The number of hours incomplete parts and binaries will be retained.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="releasedays" class="form-label fw-bold">Release Retention:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-calendar-days"></i></span>
				            <input id="releasedays" class="form-control" name="releaseretentiondays" type="text" value="{$site->releaseretentiondays}"/>
				        </div>
				        <small class="text-muted">The number of days releases will be retained for use throughout site. Set to 0 to disable.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="miscotherretentionhours" class="form-label fw-bold">Other->Misc Retention Hours:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-hourglass"></i></span>
				            <input id="miscotherretentionhours" class="form-control" name="miscotherretentionhours" type="text" value="{$site->miscotherretentionhours}"/>
				        </div>
				        <small class="text-muted">The number of hours releases categorized as Misc->Other will be retained. Set to 0 to disable.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="mischashedretentionhours" class="form-label fw-bold">Other->Hashed Retention Hours:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-hashtag"></i></span>
				            <input id="mischashedretentionhours" class="form-control" name="mischashedretentionhours" type="text" value="{$site->mischashedretentionhours}"/>
				        </div>
				        <small class="text-muted">The number of hours releases categorized as Misc->Hashed will be retained. Set to 0 to disable.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="partsdeletechunks" class="form-label fw-bold">Parts Delete In Chunks:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-trash"></i></span>
				            <input id="partsdeletechunks" class="form-control" name="partsdeletechunks" type="text" value="{$site->partsdeletechunks}"/>
				        </div>
				        <small class="text-muted">Default is 0 (off), which will remove parts in one go. If backfilling or importing and parts table is large, using chunks of 5000+ will speed up removal. Normal indexing is fastest with this setting at 0.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="minfilestoformrelease" class="form-label fw-bold">Minimum Files to Make a Release:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-file-alt"></i></span>
				            <input id="minfilestoformrelease" class="form-control" name="minfilestoformrelease" type="text" value="{$site->minfilestoformrelease}"/>
				        </div>
				        <small class="text-muted">The minimum number of files to make a release. i.e. if set to two, then releases which only contain one file will not be created.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="minsizetoformrelease" class="form-label fw-bold">Minimum File Size to Make a Release:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-compress"></i></span>
				            <input id="minsizetoformrelease" class="form-control" name="minsizetoformrelease" type="text" value="{$site->minsizetoformrelease}"/>
				        </div>
				        <small class="text-muted">The minimum total size in bytes to make a release. If set to 0, then ignored.</small>
				    </div>
				</div>

				<div class="row mb-4">
				    <div class="col-lg-3 col-md-4">
				        <label for="maxsizetoformrelease" class="form-label fw-bold">Maximum File Size to Make a Release:</label>
				    </div>
				    <div class="col-lg-9 col-md-8">
				        <div class="input-group">
				            <span class="input-group-text"><i class="fa fa-expand"></i></span>
				            <input id="maxsizetoformrelease" class="form-control" name="maxsizetoformrelease" type="text" value="{$site->maxsizetoformrelease}"/>
				        </div>
				        <small class="text-muted">The maximum total size in bytes to make a release. If set to 0, then ignored. Only deletes during release creation.</small>
				    </div>
				</div>

				<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="completionpercent" class="form-label fw-bold">Minimum Completion Percent:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-percentage"></i></span>
		            <input id="completionpercent" class="form-control" name="completionpercent" type="text" value="{$site->completionpercent}"/>
		        </div>
		        <small class="text-muted">The minimum completion percent to make a release. i.e. if set to 97, then releases under 97% completion will not be created. If set to 0, then ignored.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="grabstatus" class="form-label fw-bold">Update Grabs:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-sync"></i></span>
		            <select id="grabstatus" name="grabstatus" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->grabstatus}
		            </select>
		        </div>
		        <small class="text-muted">Whether to update download counts when someone downloads a release.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="crossposttime" class="form-label fw-bold">Crossposted Time Check:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-clock"></i></span>
		            <input id="crossposttime" class="form-control" name="crossposttime" type="text" value="{$site->crossposttime}"/>
		        </div>
		        <small class="text-muted">The time in hours to check for crossposted releases - this will delete 1 of the releases if the 2 are posted by the same person in the same time period.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxmssgs" class="form-label fw-bold">Max Messages:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
		            <input id="maxmssgs" class="form-control" name="maxmssgs" type="text" value="{$site->maxmssgs}"/>
		        </div>
		        <small class="text-muted">The maximum number of messages to fetch at a time from the server.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="max_headers_iteration" class="form-label fw-bold">Max Headers Iteration:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-list-ol"></i></span>
		            <input id="max_headers_iteration" class="form-control" name="max_headers_iteration" type="text" value="{$site->max_headers_iteration}"/>
		        </div>
		        <small class="text-muted">The maximum number of headers that update binaries sees as the total range. This ensures that a total of no more than this is attempted to be downloaded at one time per group.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="newgroupscanmethod" class="form-label fw-bold">Where to Start New Groups:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group mb-2">
		            <span class="input-group-text"><i class="fa fa-question-circle"></i></span>
		            <select id="newgroupscanmethod" name="newgroupscanmethod" class="form-select">
		                {html_options values=$yesno_ids output=$newgroupscan_names selected=$site->newgroupscanmethod}
		            </select>
		        </div>
		        <div class="input-group mb-2">
		            <span class="input-group-text"><i class="fa fa-calendar"></i></span>
		            <input id="newgroupdaystoscan" class="form-control" name="newgroupdaystoscan" type="text" value="{$site->newgroupdaystoscan}"/>
		            <span class="input-group-text">Days</span>
		        </div>
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-comment-alt"></i></span>
		            <input id="newgroupmsgstoscan" class="form-control" name="newgroupmsgstoscan" type="text" value="{$site->newgroupmsgstoscan}"/>
		            <span class="input-group-text">Posts</span>
		        </div>
		        <small class="text-muted">Scan back X (posts/days) for each new group? Can backfill to scan further.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="safebackfilldate" class="form-label fw-bold">Safe Backfill Date:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-calendar-alt"></i></span>
		            <input id="safebackfilldate" class="form-control" name="safebackfilldate" type="text" value="{$site->safebackfilldate}"/>
		        </div>
		        <small class="text-muted">The target date for safe backfill. Format: YYYY-MM-DD</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="disablebackfillgroup" class="form-label fw-bold">Auto Disable Groups During Backfill:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-power-off"></i></span>
		            <select id="disablebackfillgroup" name="disablebackfillgroup" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->disablebackfillgroup}
		            </select>
		        </div>
		        <small class="text-muted">Whether to disable a group automatically during backfill if the target date has been reached.</small>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Lookup Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookuptv" class="form-label fw-bold">Lookup TV:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-tv"></i></span>
		            <select id="lookuptv" name="lookuptv" class="form-select">
		                {html_options values=$lookuptv_ids output=$lookuptv_names selected=$site->lookuptv}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup TvRage ids on the web.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookupbooks" class="form-label fw-bold">Lookup Books:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-book"></i></span>
		            <select id="lookupbooks" name="lookupbooks" class="form-select">
		                {html_options values=$lookupbooks_ids output=$lookupbooks_names selected=$site->lookupbooks}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup book information from Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="book_reqids" class="form-label fw-bold">Type of books to look up:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-list"></i></span>
		            <select id="book_reqids" name="book_reqids[]" class="form-select" multiple>
		                {html_options values=$book_reqids_ids output=$book_reqids_names selected=$book_reqids_selected}
		            </select>
		        </div>
		        <small class="text-muted">Categories of Books to lookup information for (only work if Lookup Books is set to yes).</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookupimdb" class="form-label fw-bold">Lookup Movies:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-film"></i></span>
		            <select id="lookupimdb" name="lookupimdb" class="form-select">
		                {html_options values=$lookupmovies_ids output=$lookupmovies_names selected=$site->lookupimdb}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup film information from IMDB or TheMovieDB.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookuplanguage" class="form-label fw-bold">Movie Lookup Language:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-language"></i></span>
		            <select id="lookuplanguage" name="lookuplanguage" class="form-select">
		                {html_options values=$lookuplanguage_iso output=$lookuplanguage_names selected=$site->lookuplanguage}
		            </select>
		        </div>
		        <small class="text-muted">Preferred language for scraping external sources.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookupanidb" class="form-label fw-bold">Lookup AniDB:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-dragon"></i></span>
		            <select id="lookupanidb" name="lookupanidb" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->lookupanidb}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup anime information from AniDB when processing binaries.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookupmusic" class="form-label fw-bold">Lookup Music:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-music"></i></span>
		            <select id="lookupmusic" name="lookupmusic" class="form-select">
		                {html_options values=$lookupmusic_ids output=$lookupmusic_names selected=$site->lookupmusic}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup music information from Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="saveaudiopreview" class="form-label fw-bold">Save Audio Preview:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-music"></i></span>
		            <select id="saveaudiopreview" name="saveaudiopreview" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->saveaudiopreview}
		            </select>
		        </div>
		        <small class="text-muted">Whether to save a preview of an audio release (requires deep rar inspection enabled).<br>It is advisable to specify a path to the lame binary to reduce the size of audio previews.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookupgames" class="form-label fw-bold">Lookup Games:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-gamepad"></i></span>
		            <select id="lookupgames" name="lookupgames" class="form-select">
		                {html_options values=$lookupgames_ids output=$lookupgames_names selected=$site->lookupgames}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup game information from Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="lookupxxx" class="form-label fw-bold">Lookup XXX:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-video"></i></span>
		            <select id="lookupxxx" name="lookupxxx" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->lookupxxx}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to lookup XXX information when processing binaries.</small>
		    </div>
		</div>
		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Language/Categorization Options</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="categorizeforeign" class="form-label fw-bold">Categorize Foreign:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-globe"></i></span>
		            <select id="categorizeforeign" name="categorizeforeign" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->categorizeforeign}
		            </select>
		        </div>
		        <small class="text-muted">Whether to send foreign movies/tv to foreign sections or not. If set to true they will go in foreign categories.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="catwebdl" class="form-label fw-bold">Categorize WEB-DL:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-cloud-download-alt"></i></span>
		            <select id="catwebdl" name="catwebdl" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->catwebdl}
		            </select>
		        </div>
		        <small class="text-muted">Whether to send WEB-DL to the WEB-DL section or not. If set to true they will go in WEB-DL category, false will send them in HD TV. This will also make them inaccessible to Sickbeard and possibly Couchpotato.</small>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Password Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="end" class="form-label fw-bold">Download last compressed file:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-file-archive"></i></span>
		            <select id="end" name="end" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->end}
		            </select>
		        </div>
		        <small class="text-muted">Try to download the last rar or zip file? (This is good if most of the files are at the end.) Note: The first rar/zip is still downloaded.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="showpasswordedrelease" class="form-label fw-bold">Show Passworded Releases:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-lock"></i></span>
		            <select id="showpasswordedrelease" name="showpasswordedrelease" class="form-select">
		                {html_options values=$passworded_ids output=$passworded_names selected=$site->showpasswordedrelease}
		            </select>
		        </div>
		        <small class="text-muted">Whether to show passworded releases in browse, search, api and rss feeds.</small>
		    </div>
		</div>
		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Additional Usenet Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxsizetopostprocess" class="form-label fw-bold">Maximum Release Size to Post Process:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-file-archive"></i></span>
		            <input id="maxsizetopostprocess" class="form-control" name="maxsizetopostprocess" type="text" value="{$site->maxsizetopostprocess}"/>
		            <span class="input-group-text">GB</span>
		        </div>
		        <small class="text-muted">The maximum size in gigabytes to postprocess a release. If set to 0, then ignored.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="minsizetopostprocess" class="form-label fw-bold">Minimum Release Size to Post Process:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-file-archive"></i></span>
		            <input id="minsizetopostprocess" class="form-control" name="minsizetopostprocess" type="text" value="{$site->minsizetopostprocess}"/>
		            <span class="input-group-text">MB</span>
		        </div>
		        <small class="text-muted">The minimum size in megabytes to post process (additional) a release. If set to 0, then ignored.</small>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Advanced Settings - For advanced users</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxnzbsprocessed" class="form-label fw-bold">Maximum NZBs stage5:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-file-code"></i></span>
		            <input id="maxnzbsprocessed" class="form-control" name="maxnzbsprocessed" type="text" value="{$site->maxnzbsprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of NZB files to create on stage 5 at a time in update_releases. If more are to be created it will loop stage 5 until none remain.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="partrepair" class="form-label fw-bold">Part Repair:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-toolbox"></i></span>
		            <select id="partrepair" name="partrepair" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->partrepair}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to repair parts or not, increases backfill/binaries updating time.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="safepartrepair" class="form-label fw-bold">Part Repair for Backfill Scripts:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-shield-alt"></i></span>
		            <select id="safepartrepair" name="safepartrepair" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->safepartrepair}
		            </select>
		        </div>
		        <small class="text-muted">Whether to put unreceived parts into missed_parts table when running binaries(safe) or backfill scripts.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxpartrepair" class="form-label fw-bold">Maximum repair per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-tools"></i></span>
		            <input id="maxpartrepair" class="form-control" name="maxpartrepair" type="text" value="{$site->maxpartrepair}"/>
		        </div>
		        <small class="text-muted">The maximum amount of articles to attempt to repair at a time. If you notice that you are getting a lot of parts into the missed_parts table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads to safe scripts or stop using safe scripts until improves.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="partrepairmaxtries" class="form-label fw-bold">Maximum repair tries:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-redo"></i></span>
		            <input id="partrepairmaxtries" class="form-control" name="partrepairmaxtries" type="text" value="{$site->partrepairmaxtries}"/>
		        </div>
		        <small class="text-muted">Maximum amount of times to try part repair.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="processjpg" class="form-label fw-bold">Process JPG:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-image"></i></span>
		            <select id="processjpg" name="processjpg" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->processjpg}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to retrieve a JPG file while additional post processing, these are usually on XXX releases.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="processthumbnails" class="form-label fw-bold">Process Video Thumbnails:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-file-image"></i></span>
		            <select id="processthumbnails" name="processthumbnails" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->processthumbnails}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to process a video thumbnail image. You must have ffmpeg for this.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="processvideos" class="form-label fw-bold">Process Video Samples:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-film"></i></span>
		            <select id="processvideos" name="processvideos" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->processvideos}
		            </select>
		        </div>
		        <small class="text-muted">Whether to attempt to process a video sample, these videos are very short 1-3 seconds, 100KB on average, in ogg video format. You must have ffmpeg for this.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="segmentstodownload" class="form-label fw-bold">Number of Segments to download:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-download"></i></span>
		            <input id="segmentstodownload" class="form-control" name="segmentstodownload" type="text" value="{$site->segmentstodownload}"/>
		        </div>
		        <small class="text-muted">The maximum number of segments to download to generate the sample video file or jpg sample image. (Default 2)</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="ffmpeg_duration" class="form-label fw-bold">Video sample file duration:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-film"></i></span>
		            <input id="ffmpeg_duration" class="form-control" name="ffmpeg_duration" type="text" value="{$site->ffmpeg_duration}"/>
		            <span class="input-group-text">seconds</span>
		        </div>
		        <small class="text-muted">The maximum duration (in seconds) for ffmpeg to generate the sample for. (Default 5)</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxnestedlevels" class="form-label fw-bold">Nested archive depth:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
		            <input id="maxnestedlevels" class="form-control" name="maxnestedlevels" type="text" value="{$site->maxnestedlevels}"/>
		            <span class="input-group-text">levels</span>
		        </div>
		        <small class="text-muted">If a rar/zip has rar/zip inside of it, how many times should we go in those inner rar/zip files.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="innerfileblacklist" class="form-label fw-bold">Inner file black list Regex:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-ban"></i></span>
		            <textarea rows="3" placeholder="Example: /setup\.exe|password\.url/i" id="innerfileblacklist" class="form-control" name="innerfileblacklist">{$site->innerfileblacklist}</textarea>
		        </div>
		        <small class="text-muted">You can add a regex here to set releases to potentially passworded when a file name inside a rar/zip matches this regex. <strong>You must ensure this regex is valid, a non valid regex will cause errors during processing!</strong></small>
		    </div>
		</div>
		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Movie Trailer Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="trailers_display" class="form-label fw-bold">Fetch/Display Movie Trailers:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-play-circle"></i></span>
		            <select id="trailers_display" name="trailers_display" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->trailers_display}
		            </select>
		        </div>
		        <small class="text-muted">Fetch and display trailers from TraktTV (Requires API key) and/or TrailerAddict on the details page?</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="trailers_size_x" class="form-label fw-bold">Trailers Width:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-arrows-alt-h"></i></span>
		            <input id="trailers_size_x" class="form-control" name="trailers_size_x" type="text" value="{$site->trailers_size_x}"/>
		            <span class="input-group-text">px</span>
		        </div>
		        <small class="text-muted">Maximum width in pixels for the trailer window. (Default: 480)</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="trailers_size_y" class="form-label fw-bold">Trailers Height:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-arrows-alt-v"></i></span>
		            <input id="trailers_size_y" class="form-control" name="trailers_size_y" type="text" value="{$site->trailers_size_y}"/>
		            <span class="input-group-text">px</span>
		        </div>
		        <small class="text-muted">Maximum height in pixels for the trailer window. (Default: 345)</small>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Advanced - Postprocessing Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="timeoutseconds" class="form-label fw-bold">Time in seconds to kill unrar/7zip/mediainfo/ffmpeg/avconv:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-clock"></i></span>
		            <input id="timeoutseconds" class="form-control" name="timeoutseconds" type="text" value="{$site->timeoutseconds}"/>
		            <span class="input-group-text">seconds</span>
		        </div>
		        <small class="text-muted">How much time to wait for unrar/7zip/mediainfo/ffmpeg/avconv before killing it, set to 0 to disable. 60 is a good value. Requires the GNU Timeout path to be set.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxaddprocessed" class="form-label fw-bold">Maximum add PP per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-list-ol"></i></span>
		            <input id="maxaddprocessed" class="form-control" name="maxaddprocessed" type="text" value="{$site->maxaddprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of releases to process for passwords/previews/mediainfo per run. Every release gets processed here. This uses NNTP an connection, 1 per thread. This does not query Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxpartsprocessed" class="form-label fw-bold">Maximum add PP parts downloaded:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-download"></i></span>
		            <input id="maxpartsprocessed" class="form-control" name="maxpartsprocessed" type="text" value="{$site->maxpartsprocessed}"/>
		        </div>
		        <small class="text-muted">If a part fails to download while post processing, this will retry up to the amount you set, then give up.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="passchkattempts" class="form-label fw-bold">Maximum add PP parts checked:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-check-double"></i></span>
		            <input id="passchkattempts" class="form-control" name="passchkattempts" type="text" value="{$site->passchkattempts}"/>
		        </div>
		        <small class="text-muted">This overrides the above setting if set above 1. How many parts to check for a password before giving up. This slows down post processing massively, better to leave it 1.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxrageprocessed" class="form-label fw-bold">Maximum TVRage per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-tv"></i></span>
		            <input id="maxrageprocessed" class="form-control" name="maxrageprocessed" type="text" value="{$site->maxrageprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of TV shows to process with TVRage per run. This does not use an NNTP connection or query Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maximdbprocessed" class="form-label fw-bold">Maximum movies per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-film"></i></span>
		            <input id="maximdbprocessed" class="form-control" name="maximdbprocessed" type="text" value="{$site->maximdbprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of movies to process with IMDB per run. This does not use an NNTP connection or query Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxanidbprocessed" class="form-label fw-bold">Maximum anidb per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-dragon"></i></span>
		            <input id="maxanidbprocessed" class="form-control" name="maxanidbprocessed" type="text" value="{$site->maxanidbprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of anime to process with anidb per run. This does not use an NNTP connection or query Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxmusicprocessed" class="form-label fw-bold">Maximum music per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-music"></i></span>
		            <input id="maxmusicprocessed" class="form-control" name="maxmusicprocessed" type="text" value="{$site->maxmusicprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of music to process with amazon per run. This does not use an NNTP connection.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxgamesprocessed" class="form-label fw-bold">Maximum games per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-gamepad"></i></span>
		            <input id="maxgamesprocessed" class="form-control" name="maxgamesprocessed" type="text" value="{$site->maxgamesprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of games to process with amazon per run. This does not use an NNTP connection.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxbooksprocessed" class="form-label fw-bold">Maximum books per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-book"></i></span>
		            <input id="maxbooksprocessed" class="form-control" name="maxbooksprocessed" type="text" value="{$site->maxbooksprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of books to process with amazon per run. This does not use an NNTP connection</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="maxxxxprocessed" class="form-label fw-bold">Maximum xxx per run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-video"></i></span>
		            <input id="maxxxxprocessed" class="form-control" name="maxxxxprocessed" type="text" value="{$site->maxxxxprocessed}"/>
		        </div>
		        <small class="text-muted">The maximum amount of XXX to process per run. This does not use an NNTP connection or query Amazon.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="fixnamesperrun" class="form-label fw-bold">fixReleaseNames per Run:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-edit"></i></span>
		            <input id="fixnamesperrun" class="form-control" name="fixnamesperrun" type="text" value="{$site->fixnamesperrun}"/>
		        </div>
		        <small class="text-muted">The maximum number of releases to check per run (threaded script only).</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="amazonsleep" class="form-label fw-bold">Amazon sleep time:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-hourglass"></i></span>
		            <input id="amazonsleep" class="form-control" name="amazonsleep" type="text" value="{$site->amazonsleep}"/>
		            <span class="input-group-text">ms</span>
		        </div>
		        <small class="text-muted">Sleep time in milliseconds to wait in between amazon requests. If you thread post-proc, multiply by the number of threads. ie Postprocessing Threads = 12, Amazon sleep time = 12000<br/><a href="https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html" class="text-decoration-underline">https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html</a></small>
		    </div>
		</div>

		<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">NFO Processing Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="lookupnfo" class="form-label fw-bold">Lookup NFO:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-file-alt"></i></span>
            <select id="lookupnfo" name="lookupnfo" class="form-select">
                {html_options values=$yesno_ids output=$yesno_names selected=$site->lookupnfo}
            </select>
        </div>
        <small class="text-muted">Whether to attempt to retrieve an nfo file from usenet.<br/>
            <strong>NOTE: disabling nfo lookups will disable movie lookups.</strong>
        </small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="maxnfoprocessed" class="form-label fw-bold">Maximum NFO files per run:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-file-text"></i></span>
            <input id="maxnfoprocessed" class="form-control" name="maxnfoprocessed" type="text" value="{$site->maxnfoprocessed}"/>
        </div>
        <small class="text-muted">The maximum amount of NFO files to process per run. This uses NNTP an connection, 1 per thread. This does not query Amazon.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="maxsizetoprocessnfo" class="form-label fw-bold">Maximum Release Size to process NFOs:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-upload"></i></span>
            <input id="maxsizetoprocessnfo" class="form-control" name="maxsizetoprocessnfo" type="text" value="{$site->maxsizetoprocessnfo}"/>
            <span class="input-group-text">GB</span>
        </div>
        <small class="text-muted">The maximum size in gigabytes of a release to process it for NFOs. If set to 0, then ignored.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="minsizetoprocessnfo" class="form-label fw-bold">Minimum Release Size to process NFOs:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-download"></i></span>
            <input id="minsizetoprocessnfo" class="form-control" name="minsizetoprocessnfo" type="text" value="{$site->minsizetoprocessnfo}"/>
            <span class="input-group-text">MB</span>
        </div>
        <small class="text-muted">The minimum size in megabytes of a release to process it for NFOs. If set to 0, then ignored.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="maxnforetries" class="form-label fw-bold">Maximum amount of times to redownload a NFO:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-refresh"></i></span>
            <input id="maxnforetries" class="form-control" name="maxnforetries" type="text" value="{$site->maxnforetries}"/>
            <span class="input-group-text">times</span>
        </div>
        <small class="text-muted">How many times to retry when a NFO fails to download. If set to 0, we will not retry. The max is 7.</small>
    </div>
</div>
		<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Connection Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="nntpretries" class="form-label fw-bold">NNTP Retry Attempts:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-refresh"></i></span>
            <input id="nntpretries" class="form-control" name="nntpretries" type="text" value="{$site->nntpretries}"/>
        </div>
        <small class="text-muted">The maximum number of retry attempts to connect to nntp provider. On error, each retry takes approximately 5 seconds nntp returns reply. (Default 10)</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="delaytime" class="form-label fw-bold">Delay Time Check:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="delaytime" class="form-control" name="delaytime" type="text" value="{$site->delaytime}"/>
        </div>
        <small class="text-muted">The time in hours to wait, since last activity, before releases without parts counts in the subject are are created.<br>Setting this below 2 hours could create incomplete releases.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="collection_timeout" class="form-label fw-bold">Collection Timeout Check:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-hourglass-end"></i></span>
            <input id="collection_timeout" class="form-control" name="collection_timeout" type="text" value="{$site->collection_timeout}"/>
        </div>
        <small class="text-muted">How many hours to wait before converting a collection into a release that is considered "stuck".<br>Default value is 48 hours.</small>
    </div>
</div>

<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Developer Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="showdroppedyencparts" class="form-label fw-bold">Log Dropped Headers:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-bug"></i></span>
            <select id="showdroppedyencparts" name="showdroppedyencparts" class="form-select">
                {html_options values=$yesno_ids output=$yesno_names selected=$site->showdroppedyencparts}
            </select>
        </div>
        <small class="text-muted">For developers. Whether to log all headers that have 'yEnc' and are dropped. Logged to not_yenc/groupname.dropped.txt.</small>
    </div>
</div>

<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Advanced - Threaded Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="binarythreads" class="form-label fw-bold">Update Binaries Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="binarythreads" class="form-control" name="binarythreads" type="text" value="{$site->binarythreads}"/>
        </div>
        <small class="text-muted">The number of threads for update_binaries. If you notice that you are getting a lot of parts into the missed_parts table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads. At least until the cause can be determined.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="backfillthreads" class="form-label fw-bold">Backfill Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="backfillthreads" class="form-control" name="backfillthreads" type="text" value="{$site->backfillthreads}"/>
        </div>
        <small class="text-muted">The number of threads for backfill.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="releasethreads" class="form-label fw-bold">Update Releases Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="releasethreads" class="form-control" name="releasethreads" type="text" value="{$site->releasethreads}"/>
        </div>
        <small class="text-muted">The number of threads for releases update scripts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="postthreads" class="form-label fw-bold">Postprocessing Additional Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="postthreads" class="form-control" name="postthreads" type="text" value="{$site->postthreads}"/>
        </div>
        <small class="text-muted">The number of threads for additional postprocessing. This includes deep rar inspection, preview and sample creation and nfo processing.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="nfothreads" class="form-label fw-bold">NFO Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="nfothreads" class="form-control" name="nfothreads" type="text" value="{$site->nfothreads}"/>
        </div>
        <small class="text-muted">The number of threads for nfo postprocessing. The max is 16, if you set anything higher it will use 16.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="postthreadsnon" class="form-label fw-bold">Postprocessing Non-Amazon Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="postthreadsnon" class="form-control" name="postthreadsnon" type="text" value="{$site->postthreadsnon}"/>
        </div>
        <small class="text-muted">The number of threads for non-amazon postprocessing. This includes movies, anime and tv lookups.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="fixnamethreads" class="form-label fw-bold">fixReleaseNames Threads:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <input id="fixnamethreads" class="form-control" name="fixnamethreads" type="text" value="{$site->fixnamethreads}"/>
        </div>
        <small class="text-muted">The number of threads for fixReleasesNames. This includes md5, nfos, par2 and filenames.</small>
    </div>
</div>

<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">User Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="registerstatus" class="form-label fw-bold">Registration Status:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-user-plus"></i></span>
            <select id="registerstatus" name="registerstatus" class="form-select">
                {html_options values=$registerstatus_ids output=$registerstatus_names selected=$site->registerstatus}
            </select>
        </div>
        <small class="text-muted">The status of registrations to the site.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="userdownloadpurgedays" class="form-label fw-bold">User Downloads Purge Days:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-calendar"></i></span>
            <input id="userdownloadpurgedays" class="form-control" name="userdownloadpurgedays" type="text" value="{$site->userdownloadpurgedays}"/>
        </div>
        <small class="text-muted">The number of days to preserve user download history, for use when checking limits being hit. Set to zero will remove all records of what users download, but retain history of when, so that role based limits can still be applied.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="userhostexclusion" class="form-label fw-bold">IP Whitelist:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-shield"></i></span>
            <input id="userhostexclusion" class="form-control" name="userhostexclusion" type="text" value="{$site->userhostexclusion}"/>
        </div>
        <small class="text-muted">A comma separated list of IP addresses which will be excluded from user limits on number of requests and downloads per IP address. Include values for google reader and other shared services which may be being used.</small>
    </div>
</div>

<div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
    <button type="submit" class="btn btn-success">
        <i class="fa fa-save me-2"></i>Save Site Settings
    </button>
</div>
