import { gql } from 'apollo-boost';

import shiftHoursFragment from 'components/ShiftPlanner/fragments/shiftHoursFragment';
import shiftRoleTypeFragment from 'components/ShiftPlanner/fragments/shiftRoleTypeFragment';

const shiftDayFragment = gql`
  ${shiftHoursFragment}
  ${shiftRoleTypeFragment}
  fragment ShiftDay on ShiftDay {
    id
    day
    shiftRoles {
      id
      halfHour
      ...ShiftRoleType
      ...ShiftHours
    }
  }
`;

export default shiftDayFragment;
