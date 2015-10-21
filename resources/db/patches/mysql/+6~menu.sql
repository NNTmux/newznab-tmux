# Remove calendar, let sonarr/sickbeard/sicjrage handle it
DELETE FROM menu WHERE href = 'calendar';
