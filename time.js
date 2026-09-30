function formatRecordTimes(records, startedAt, timeZone) {
  let legacyDate = new Date(startedAt);
  let previousLegacySeconds = -1;
  return records.map(record => {
    let date = new Date(record.time);
    if (Number.isNaN(date.getTime())) {
      const match = /^(\d{2}):(\d{2}):(\d{2})$/.exec(record.time);
      if (!match || Number.isNaN(legacyDate.getTime())) return record.time;
      const [, hours, minutes, seconds] = match;
      const legacySeconds = Number(hours) * 3600 + Number(minutes) * 60 + Number(seconds);
      if (legacySeconds < previousLegacySeconds) legacyDate.setUTCDate(legacyDate.getUTCDate() + 1);
      previousLegacySeconds = legacySeconds;
      date = new Date(Date.UTC(
        legacyDate.getUTCFullYear(),
        legacyDate.getUTCMonth(),
        legacyDate.getUTCDate(),
        Number(hours),
        Number(minutes),
        Number(seconds)
      ));
    }
    return date.toLocaleTimeString('zh-CN', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23'
    });
  });
}
