import { gql } from 'apollo-boost';

const USER = gql`
  query UserFindById($id: Int!) {
    userFindById(id: $id) {
      id
      name
      surname
      totalEvaluationScore
      evaluation {
        id
        date
        positive
        description
        evaluator {
          id
          name
          surname
        }
      }
    }
  }
`;

export default USER;
