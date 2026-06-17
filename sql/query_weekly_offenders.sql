SELECT * FROM (
    SELECT plate, count(1) as count, group_concat(firstphoto order by firstphoto) as firstphotos from (
        SELECT plate, min(convert_tz(phototime,'+00:00', '+11:00')) as firstphoto, date(convert_tz(phototime,'+00:00', '+11:00')) as photodate 
          FROM parking_records 
         WHERE phototime > NOW() - INTERVAL (2 * 24 +10 ) HOUR 
         GROUP BY plate, photodate
) al GROUP BY plate
) al2 WHERE count > 2;
