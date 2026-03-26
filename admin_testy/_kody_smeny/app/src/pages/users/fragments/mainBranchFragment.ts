import { gql } from 'apollo-boost';

const mainBranchFragment = gql`
  fragment MainBranch on User {
    mainBranch {
      id
      name
    }
  }
`;

export default mainBranchFragment;
