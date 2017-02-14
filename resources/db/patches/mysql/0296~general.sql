DROP PROCEDURE IF EXISTS delete_release;

DELIMITER $$

CREATE PROCEDURE delete_release(IN is_numeric BOOLEAN, IN identifier VARCHAR(40))
  COMMENT 'Cascade deletes release from child tables when parent row is deleted'
  COMMENT 'If is_numeric is true, identifier should be the releases_id, if false the guid'

  main:BEGIN

    DECLARE where_constr VARCHAR(255) DEFAULT '';

    IF is_numeric IS TRUE
    THEN SET where_constr = CONCAT('r.id = ', identifier);

    ELSEIF is_numeric IS FALSE
    THEN SET where_constr = CONCAT("r.guid = '", identifier, "'");

    ELSE LEAVE main;
    END IF;

    DELETE r, rn, rc, uc, rf, ra, rs, rv, re, df, rg, rr
    FROM releases r
      LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
      LEFT OUTER JOIN release_comments rc ON rc.releases_id = r.id
      LEFT OUTER JOIN users_releases uc ON uc.releases_id = r.id
      LEFT OUTER JOIN release_files rf ON rf.releases_id = r.id
      LEFT OUTER JOIN audio_data ra ON ra.releases_id = r.id
      LEFT OUTER JOIN release_subtitles rs ON rs.releases_id = r.id
      LEFT OUTER JOIN video_data rv ON rv.releases_id = r.id
      LEFT OUTER JOIN releaseextrafull re ON re.releases_id = r.id
      LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
      LEFT OUTER JOIN releases_groups rg ON rg.releases_id = r.id
      LEFT OUTER JOIN release_regexes rr ON rr.releases_id = r.id
    WHERE where_constr;

  END; $$

DELIMITER ;