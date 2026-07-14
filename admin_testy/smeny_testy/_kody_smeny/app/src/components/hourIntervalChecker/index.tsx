const hourIntervalChecker = (
  start: string,
  end: string,
  dayStart: number,
): boolean => {
  if ((start === '' && end !== '') || (start !== '' && end === ''))
    return false;
  if (start === '' && end === '') return true;
  if (start === end) return false;

  if (+end >= 0 && +end < 24 && +start >= 0 && +start < 24) {
    for (let i = +start; i !== +end; i++) {
      if (i > 24) {
        i = 0;
        if (+end === 0) return true;
      }
      if (i === dayStart - 1) {
        return false;
      }
    }
  } else {
    return false;
  }

  return true;
};

export default hourIntervalChecker;
