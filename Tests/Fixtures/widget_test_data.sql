-- Test data for Content Audit widgets

-- Add stale pages
INSERT INTO `pages`
    (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
    `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`, `tstamp`)
VALUES
    (100, 1, 'Stale page 1', '/stale-page-1', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 190 DAY))),
    (101, 1, 'Not so stale page 2', '/not-so-stale-page-2', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 100 DAY))),
    (102, 1, 'Very stale page 3', '/very-stale-page-3', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 400 DAY)));

-- Add fresh pages
INSERT INTO `pages`
    (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
    `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`, `tstamp`)
VALUES
    (103, 1, 'Fresh page 1', '/fresh-page-1', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP()),
    (104, 1, 'Not so fresh page 2', '/not-so-fresh-page-2', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 100 DAY))),
    (105, 1, 'Very fresh page 3', '/very-fresh-page-3', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)));

-- Add long hidden pages
INSERT INTO `pages`
    (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
    `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`, `hidden`, `tstamp`)
VALUES
    (106, 1, 'Long hidden page 1', '/long-hidden-page-1', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', 1, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 800 DAY))),
    (107, 1, 'Not so long hidden page 2', '/not-so-long-hidden-page-2', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', 1, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 200 DAY))),
    (108, 1, 'Very long hidden page 3', '/very-long-hidden-page-3', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', 1, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 500 DAY)));

-- Add long hidden content elements
INSERT INTO tt_content
    (uid, pid, CType, header, bodytext, colPos, sys_language_uid, l18n_parent, hidden, tstamp)
VALUES
    (100, 1, 'text', 'Long hidden content 1', '<p>This content has been hidden for a long time.</p>', 0, 0, 0, 1, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 800 DAY))),
    (101, 3, 'text', 'Not so long hidden content 2', '<p>This content is hidden but not for so long.</p>', 0, 0, 0, 1, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 450 DAY))),
    (102, 3, 'text', 'Very long hidden content 3', '<p>This content is also hidden for a very long time.</p>', 0, 0, 0, 1, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 450 DAY)));

-- Add content to test (really) empty pages
-- Add visible content to contact page (7), hidden content to imprint (9),
-- deleted content to research topics (11), and leave research groups (13) empty
INSERT INTO tt_content
    (uid, pid, CType, header, bodytext, colPos, sys_language_uid, l18n_parent, hidden, deleted, tstamp)
VALUES
    (110, 7, 'text', 'Get in touch', '<p>Contact us via email or phone.</p>', 0, 0, 0, 0, 0, UNIX_TIMESTAMP()),
    (111, 9, 'text', 'Hidden imprint content', '<p>This content is hidden.</p>', 0, 0, 0, 1, 0, UNIX_TIMESTAMP()),
    (112, 11, 'text', 'Deleted content', '<p>This content is deleted.</p>', 0, 0, 0, 0, 1, UNIX_TIMESTAMP());

-- Add sys_file entries for testing missing image fields
INSERT INTO `sys_file`
    (`uid`, `pid`, `tstamp`, `last_indexed`, `missing`, `storage`, `type`, `metadata`, `identifier`, `identifier_hash`, `extension`, `mime_type`, `name`, `sha1`, `size`, `creation_date`, `modification_date`)
VALUES
    (100, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 0, '/test-images/image1.jpg', SHA1('/test-images/image1.jpg'), 'jpg', 'image/jpeg', 'image1.jpg', SHA1('dummy'), 12345, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (101, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 0, '/test-images/image2.jpg', SHA1('/test-images/image2.jpg'), 'jpg', 'image/jpeg', 'image2.jpg', SHA1('dummy'), 12345, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `sys_file_metadata`
    (`uid`, `pid`, `tstamp`, `crdate`, `file`, `title`, `description`, `alternative`)
VALUES
    (100, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 100, 'Test Image 1', 'This image is missing alt text', ''),
    (101, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 101, 'Test Image 2 with Alt', 'This image has alt text', 'Proper alt text here');

INSERT INTO `sys_file_reference`
    (`uid`, `pid`, `tstamp`, `crdate`, `uid_local`, `uid_foreign`, `tablenames`, `fieldname`)
VALUES
    (100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 100, 5, 'tt_content', 'assets'),
    (101, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 101, 7, 'tt_content', 'assets');

-- Enable external link checking in linkvalidator
UPDATE `pages` SET `TSconfig` = 'mod.linkvalidator.linktypes = db,file,external' WHERE `uid` = 1;

-- Pages for broken link tests
INSERT INTO `pages`
    (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
    `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`, `tstamp`)
VALUES
    (200, 1, 'Page with many broken links', '/page-with-many-broken-links', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))),
    (201, 1, 'Page with few broken links', '/page-with-few-broken-links', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 DAY))),
    (202, 1, 'Page with external broken links', '/page-with-external-broken-links', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 60 DAY))),
    (203, 1, 'Valid page target', '/valid-page-target', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP());

-- Content elements with links for testing
INSERT INTO tt_content
    (uid, pid, CType, header, bodytext, colPos, sys_language_uid, l18n_parent, hidden, tstamp)
VALUES
    (200, 200, 'text', 'Content with broken internal links', '<p>Link to <a href="t3://page?uid=999">non-existing page</a> and <a href="t3://page?uid=998">another broken page</a>.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))),
    (201, 200, 'text', 'More broken links', '<p>Link to <a href="t3://record?identifier=tt_content&uid=9999">non-existing content</a>.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 25 DAY))),
    (202, 201, 'text', 'Content with one broken link', '<p>Valid link to <a href="t3://page?uid=203">existing page</a> but also <a href="t3://page?uid=997">broken page</a>.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 DAY))),
    (203, 202, 'text', 'Content with broken external links', '<p>Check <a href="https://example-broken-site-12345.com">broken external site</a> and <a href="https://another-invalid-domain-99999.org">another broken site</a>.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 60 DAY))),
    (204, 203, 'text', 'Content with valid links', '<p>Link to <a href="t3://page?uid=1">homepage</a> and <a href="https://typo3.org">TYPO3</a>.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP());

-- Prefill broken link entries in linkvalidator
INSERT INTO `tx_linkvalidator_link`
    (`uid`, `record_uid`, `record_pid`, `language`, `headline`, `field`, `table_name`, `element_type`, `link_title`, `url`, `url_response`, `last_check`, `link_type`, `needs_recheck`)
VALUES
    -- Broken internal page links on page 200 (multiple broken links)
    (1, 200, 200, 0, 'Content with broken internal links', 'bodytext', 'tt_content', 'text', 'non-existing page', '999', '{"valid":false,"errorParams":{"errorType":{"page":"notExisting"},"page":{"uid":999}}}', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'db', 0),
    (2, 200, 200, 0, 'Content with broken internal links', 'bodytext', 'tt_content', 'text', 'another broken page', '998', '{"valid":false,"errorParams":{"errorType":{"page":"notExisting"},"page":{"uid":998}}}', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'db', 0),
    (3, 201, 200, 0, 'More broken links', 'bodytext', 'tt_content', 'text', 'non-existing content', '9999', '{"valid":false,"errorParams":{"errorType":{"record":"notExisting"},"record":{"uid":9999,"table":"tt_content"}}}', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'db', 0),

    -- Broken internal page link on page 201 (few broken links)
    (4, 202, 201, 0, 'Content with one broken link', 'bodytext', 'tt_content', 'text', 'broken page', '997', '{"valid":false,"errorParams":{"errorType":{"page":"notExisting"},"page":{"uid":997}}}', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 DAY)), 'db', 0),

    -- Broken external links on page 202
    (5, 203, 202, 0, 'Content with broken external links', 'bodytext', 'tt_content', 'text', 'broken external site', 'https://example-broken-site-12345.com', '{"valid":false,"errorParams":{"errorType":"libcurlErrno","exception":"cURL error 6: Could not resolve host: example-broken-site-12345.com","errno":6,"message":"Couldn\'t resolve host."}}', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'external', 0),
    (6, 203, 202, 0, 'Content with broken external links', 'bodytext', 'tt_content', 'text', 'another broken site', 'https://another-invalid-domain-99999.org', '{"valid":false,"errorParams":{"errorType":"libcurlErrno","exception":"cURL error 6: Could not resolve host: another-invalid-domain-99999.org","errno":6,"message":"Couldn\'t resolve host."}}', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'external', 0);

-- Pages and content for recent changes tests
INSERT INTO `pages`
    (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
    `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`, `crdate`, `tstamp`)
VALUES
    (300, 1, 'Recently created page', '/recently-created-page', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (301, 1, 'Recently updated page', '/recently-updated-page', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 10 DAY)), UNIX_TIMESTAMP());

INSERT INTO tt_content
    (uid, pid, CType, header, bodytext, colPos, sys_language_uid, l18n_parent, hidden, crdate, tstamp)
VALUES
    (300, 1, 'text', 'Recently created content element', '<p>This content element was just created.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (301, 1, 'text', 'Recently updated content element', '<p>This content element was edited recently.</p>', 0, 0, 0, 0, UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 5 DAY)), UNIX_TIMESTAMP());

-- Log the creation and edit in sys_log (pages/tt_content no longer track this themselves)
INSERT INTO `sys_log`
    (`uid`, `tstamp`, `userid`, `action`, `recuid`, `tablename`, `recpid`, `channel`)
VALUES
    (300, UNIX_TIMESTAMP(), 3, 1, 300, 'pages', 1, 'content'),
    (301, UNIX_TIMESTAMP(), 3, 2, 301, 'pages', 1, 'content'),
    (302, UNIX_TIMESTAMP(), 3, 1, 300, 'tt_content', 1, 'content'),
    (303, UNIX_TIMESTAMP(), 3, 2, 301, 'tt_content', 1, 'content');

-- Abstract and file reference for missing page fields test
UPDATE `pages` SET `abstract` = 'This page already has a teaser abstract filled in.' WHERE `uid` IN (3, 100, 101, 102);
INSERT INTO `sys_file_reference`
    (`uid`, `pid`, `tstamp`, `crdate`, `uid_local`, `uid_foreign`, `tablenames`, `fieldname`)
VALUES
    (102, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 101, 7, 'pages', 'media');

-- German translations for untranslated pages test
INSERT INTO `pages`
    (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`, `tstamp`)
VALUES
    (400, 1, 'Stale page 1 (DE)', '/de/stale-page-1', 1, 100, 100, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP()),
    (401, 1, 'Very stale page 3 (DE)', '/de/very-stale-page-3', 1, 102, 102, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP()),
    (402, 1, 'Fresh page 1 (DE)', '/de/fresh-page-1', 1, 103, 103, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP()),
    (403, 1, 'Page with many broken links (DE)', '/de/page-with-many-broken-links', 1, 200, 200, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP()),
    (404, 1, 'Recently created page (DE)', '/de/recently-created-page', 1, 300, 300, 1, 1, 31, 31, 1, 1, 0, '', UNIX_TIMESTAMP());

-- Search log and search index of indexed_search for searches without results test
-- Logged words which are missing in the search index count as searches without results
INSERT INTO `index_words`
    (`wid`, `baseword`)
VALUES
    ('1', 'contact'),
    ('2', 'research');
INSERT INTO `index_stat_word`
    (`word`, `tstamp`, `pageid`)
VALUES
    ('library', UNIX_TIMESTAMP() - 86400 * 2, 1),
    ('library', UNIX_TIMESTAMP() - 86400 * 5, 1),
    ('library', UNIX_TIMESTAMP() - 86400 * 9, 1),
    ('opening hours', UNIX_TIMESTAMP() - 86400, 1),
    ('opening hours', UNIX_TIMESTAMP() - 86400 * 20, 1),
    ('vacancies', UNIX_TIMESTAMP() - 86400 * 3, 1),
    ('contact', UNIX_TIMESTAMP() - 86400, 1),
    ('contact', UNIX_TIMESTAMP() - 86400 * 4, 1),
    ('research', UNIX_TIMESTAMP() - 86400 * 6, 1),
    ('holidays', UNIX_TIMESTAMP() - 86400 * 200, 1);

-- Search log of ke_search for searches without results test
-- Logged phrases with zero hits count as searches without results
INSERT INTO `tx_kesearch_stat_search`
    (`pid`, `searchphrase`, `tstamp`, `hits`, `language`)
VALUES
    (1, 'library', UNIX_TIMESTAMP() - 86400 * 2, 0, 0),
    (1, 'library', UNIX_TIMESTAMP() - 86400 * 5, 0, 0),
    (1, 'library', UNIX_TIMESTAMP() - 86400 * 9, 0, 0),
    (1, 'opening hours', UNIX_TIMESTAMP() - 86400, 0, 0),
    (1, 'opening hours', UNIX_TIMESTAMP() - 86400 * 20, 0, 0),
    (1, 'rooftop garden', UNIX_TIMESTAMP() - 86400 * 3, 0, 0),
    (1, 'contact', UNIX_TIMESTAMP() - 86400, 4, 0),
    (1, 'contact', UNIX_TIMESTAMP() - 86400 * 4, 4, 0),
    (1, 'research', UNIX_TIMESTAMP() - 86400 * 6, 2, 0),
    (1, 'holidays', UNIX_TIMESTAMP() - 86400 * 200, 0, 0);
