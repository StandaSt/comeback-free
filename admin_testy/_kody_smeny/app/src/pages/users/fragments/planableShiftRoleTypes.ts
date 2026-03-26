import { gql } from 'apollo-boost';

const planableShiftRoleTypes = gql`
  fragment PlanableShiftRoleTypes on User {
    planableShiftRoleTypes {
      id
      name
    }
  }
`;

export default planableShiftRoleTypes;
