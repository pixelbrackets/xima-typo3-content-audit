-- Dashboard for Content Audit widgets
INSERT INTO `be_dashboards` (`identifier`, `title`, `widgets`, `crdate`, `tstamp`, `cruser_id`)
VALUES
    ('content-audit-demo-admin',
    'Content Audit',
    '{
        "contentAuditDailyMission": {"identifier": "contentAuditDailyMission"},
        "contentAuditStalePages": {"identifier": "contentAuditStalePages"},
        "contentAuditFreshPages": {"identifier": "contentAuditFreshPages"},
        "contentAuditHiddenPages": {"identifier": "contentAuditHiddenPages"},
        "contentAuditHiddenContent": {"identifier": "contentAuditHiddenContent"},
        "contentAuditEmptyPages": {"identifier": "contentAuditEmptyPages"},
        "contentAuditUntranslatedPages": {"identifier": "contentAuditUntranslatedPages"},
        "contentAuditMissingPageFields": {"identifier": "contentAuditMissingPageFields"},
        "contentAuditMissingImageFields": {"identifier": "contentAuditMissingImageFields"},
        "contentAuditBrokenLinks": {"identifier": "contentAuditBrokenLinks"},
        "contentAuditSearchesWithoutResults": {"identifier": "contentAuditSearchesWithoutResults"},
        "contentAuditRecentChanges": {"identifier": "contentAuditRecentChanges"},
        "contentAuditContentStatistics": {"identifier": "contentAuditContentStatistics"}
    }',
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    2),
    ('content-audit-demo-editor',
    'Content Audit',
    '{
        "contentAuditDailyMission": {"identifier": "contentAuditDailyMission"},
        "contentAuditStalePages": {"identifier": "contentAuditStalePages"},
        "contentAuditFreshPages": {"identifier": "contentAuditFreshPages"},
        "contentAuditHiddenPages": {"identifier": "contentAuditHiddenPages"},
        "contentAuditHiddenContent": {"identifier": "contentAuditHiddenContent"},
        "contentAuditEmptyPages": {"identifier": "contentAuditEmptyPages"},
        "contentAuditUntranslatedPages": {"identifier": "contentAuditUntranslatedPages"},
        "contentAuditMissingPageFields": {"identifier": "contentAuditMissingPageFields"},
        "contentAuditMissingImageFields": {"identifier": "contentAuditMissingImageFields"},
        "contentAuditBrokenLinks": {"identifier": "contentAuditBrokenLinks"},
        "contentAuditSearchesWithoutResults": {"identifier": "contentAuditSearchesWithoutResults"},
        "contentAuditRecentChanges": {"identifier": "contentAuditRecentChanges"},
        "contentAuditContentStatistics": {"identifier": "contentAuditContentStatistics"}
    }',
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    3);
