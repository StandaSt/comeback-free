const getNextMonday = (skipWeeks = 0): Date => {
  const futureMonday = new Date();
  futureMonday.setDate(
    futureMonday.getDate() +
      ((1 + 7 - futureMonday.getDay()) % 7) +
      skipWeeks * 7,
  );
  futureMonday.setHours(0, 0, 0, 0);

  const now = new Date(Date.now());
  now.setHours(0, 0, 0, 0);

  if (now.getDay() === 1) {
    futureMonday.setDate(futureMonday.getDate() + 7);
  }

  return futureMonday;
};

export default getNextMonday;
