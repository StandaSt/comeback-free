import { gql } from 'apollo-boost';

import shiftHoursFragment from './shiftHoursFragment';
import shiftRoleTypeFragment from './shiftRoleTypeFragment';

const shiftRoleFragment = gql`
  ${shiftHoursFragment}
  ${shiftRoleTypeFragment}
  fragment ShiftRole on ShiftRole {
    id
    ...ShiftRoleType
    ...ShiftHours
  }
`;

export default shiftRoleFragment;
