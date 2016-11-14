# Add missing settings into settings table

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'lastpretime', '0', 'Last time we downloaded a pre using WEB sources', 'lastpretime');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'currentppticket', '0', '', 'currentppticket');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'debuginfo', '0', '', 'debuginfo');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'nextppticket', '0', '', 'nextppticket');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'postdelay', '300', '', 'postdelay');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'predbversion', '1', '', 'predbversion');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'replacenzbs', '0', 'NZBs that are crossposted, instead of deleting, replace with the nzb grabbed.(This is not necessary, was added before I understood how crossposted nzbs work).', 'replacenzbs');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('', '', 'showbacks', '0', '', 'showbacks');

REPLACE INTO settings (section, subsection, name, value, hint, setting) VALUES ('APIs', 'recaptcha', 'sitekey', '', 'ReCaptcha SiteKey for generating the captcha block for input forms.', 'recaptchasitekey');

REPLACE INTO settings (section, subsection, name, value, hint, setting) VALUES ('APIs', 'recaptcha', 'secretkey', '', 'ReCaptcha SecretKey for verifying submitted captcha results.', 'recaptchasecretkey');

REPLACE INTO settings (section, subsection, name, value, hint, setting) VALUES ('APIs', 'recaptcha', 'enabled', '1', 'Whether ReCaptcha should be used or not.\nThis allows for disabling it without having to remove your keys.', 'recaptchaenabled');

INSERT INTO settings (section, subsection, name, value, hint, setting) VALUES ('APIs', 'APIKeys', 'section-label', '3<sup>rd.</sup> Party API Keys', '', '');

REPLACE INTO settings (section, subsection, name, value, hint, setting) VALUES ('apps', 'sabnzbplus', 'priority', '', 'Set the priority level for NZBs that are added to your queue', 'sabpriority');

REPLACE INTO settings (section, subsection, name, value, hint, setting) VALUES ('site', 'google', 'google_adsense_acc', '', 'AdSense account: e.g. pub-123123123123123', 'google_adsense_acc');

REPLACE INTO settings (section, subsection, name, value, hint, setting) VALUES ('site', 'main', 'menuposition', '2', 'Where the menu should appear. Moving the menu to the top will require using a theme which widens the content panel. (not currently functional)', 'menuposition');
