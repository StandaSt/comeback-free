import { gql } from 'apollo-boost';

import shiftDayFragment from './shiftDayFragment';

const shiftDaysFragment = gql`
  ${shiftDayFragment}
  fragment ShiftDays on ShiftWeek {
    shiftDays {
      ...ShiftDay
    }
  }
`;

export default shiftDaysFragment;
