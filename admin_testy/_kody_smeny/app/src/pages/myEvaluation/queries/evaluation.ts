import { gql } from 'apollo-boost';

const EVALUATION_QUERY = gql`
  {
    userGetLogged {
      evaluation {
        id
        positive
        description
      }
    }
  }
`;

export default EVALUATION_QUERY;
