import { gql } from 'apollo-boost';

const workingBranchesFragment = gql`
  fragment WorkingBranches on User {
    workingBranches {
      id
      name
    }
  }
`;

export default workingBranchesFragment;
