const hoursToIntervalsByGroup = (
  dayStart: number,
  hours: { startHour: number; group: string; halfHour?: boolean }[],
): {
  id: number;
  from: number;
  to: number;
  group: string;
  halfHour: boolean;
}[] => {
  const hourGroups = [];
  let hourGroupId = 0;
  let currentGroup;

  for (let i = dayStart; i !== dayStart - 1; i++) {
    if (i > 23) i = 0;

    const hour = hours.find(h => h.startHour === i);
    if (hour) {
      const to = i === 23 ? 0 : i + 1;
      if (currentGroup) {
        if (currentGroup.group === hour.group) {
          currentGroup.to = to;
        } else {
          hourGroups.push(currentGroup);
          currentGroup = {
            id: hourGroupId,
            from: i,
            to,
            group: hour.group,
          };
          hourGroupId++;
        }
      } else {
        currentGroup = {
          id: hourGroupId,
          from: i,
          to,
          group: hour.group,
          halfHour: hour.halfHour,
        };
        hourGroupId++;
      }
    } else if (currentGroup) {
      hourGroups.push(currentGroup);
      currentGroup = undefined;
    } else {
      currentGroup = undefined;
    }
  }
  if (currentGroup) hourGroups.push(currentGroup);

  return hourGroups;
};

export default hoursToIntervalsByGroup;
