const hoursToIntervals = (
  dayStart: number,
  hours: number[],
): { id: number; from: number; to: number }[] => {
  const hourGroups = [];
  let hourGroupId = 0;
  let currentGroup;

  for (let i = dayStart; i !== dayStart - 1; i++) {
    if (i > 23) i = 0;

    const index = hours.findIndex(h => h === i);
    if (index >= 0) {
      const to = i === 23 ? 0 : i + 1;
      if (currentGroup) {
        currentGroup.to = to;
      } else {
        currentGroup = { id: hourGroupId, from: i, to };
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

export default hoursToIntervals;
