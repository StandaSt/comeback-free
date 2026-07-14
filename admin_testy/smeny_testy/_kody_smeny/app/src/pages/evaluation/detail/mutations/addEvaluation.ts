import { gql } from 'apollo-boost';

const USER_ADD_EVALUATION = gql`
  mutation UserAddEvaluation(
    $userId: Int!
    $description: String!
    $positive: Boolean!
  ) {
    userAddEvaluation(
      userId: $userId
      description: $description
      positive: $positive
    ) {
      id
      evaluation {
        id
        description
        date
      }
    }
  }
`;

export default USER_ADD_EVALUATION;
