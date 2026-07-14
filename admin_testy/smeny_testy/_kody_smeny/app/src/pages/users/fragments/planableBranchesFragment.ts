import { gql } from 'apollo-boost';

const planableBranchesFragment = gql`
  fragment PlanableBranches on User {
    planableBranches {
      id
      name
    }
  }
`;

export default planableBranchesFragment;
