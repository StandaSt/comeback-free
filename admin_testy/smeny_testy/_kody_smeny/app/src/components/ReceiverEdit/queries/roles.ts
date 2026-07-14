import { gql } from 'apollo-boost';

const ROLES_QUERY = gql`
  {
    roleFindAll {
      id
      name
    }
  }
`;

export default ROLES_QUERY;
