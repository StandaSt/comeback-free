import { gql } from 'apollo-boost';

const workersShiftRoleTypesFragment = gql`
  fragment WorkersShiftRoleTypes on User {
    workersShiftRoleTypes {
      id
      name
    }
  }
`;

export default workersShiftRoleTypesFragment;
