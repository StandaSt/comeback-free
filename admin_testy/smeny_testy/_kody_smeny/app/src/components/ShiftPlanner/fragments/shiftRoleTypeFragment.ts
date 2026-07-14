import { gql } from 'apollo-boost';

const shiftRoleTypeFragment = gql`
  fragment ShiftRoleType on ShiftRole {
    type {
      id
      name
      sortIndex
      color
    }
  }
`;

export default shiftRoleTypeFragment;
