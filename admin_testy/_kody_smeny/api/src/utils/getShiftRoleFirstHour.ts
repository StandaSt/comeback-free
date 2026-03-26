import ShiftHour from 'shiftHour/shiftHour.entity';

const getShiftRoleFirstHour = ({
  shiftHours,
  dayStart,
}: {
  shiftHours: ShiftHour[];
  dayStart: number;
}): number | undefined => {
  for (let i = dayStart; i !== dayStart - 1; i++) {
    if (i > 23) i = 0;

    if (shiftHours.some(h => h.startHour === i)) {
      return i;
    }
  }

  return undefined;
};

export default getShiftRoleFirstHour;
