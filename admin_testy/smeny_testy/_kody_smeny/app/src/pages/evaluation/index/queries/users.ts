import { gql } from 'apollo-boost';

const USER_PAGINATE = gql`
  query UserPaginate(
    $filter: UserFilterArg
    $orderBy: OrderByArg
    $limit: Int!
    $offset: Int!
  ) {
    userPaginate {
      items(
        filter: $filter
        orderBy: $orderBy
        limit: $limit
        offset: $offset
      ) {
        id
        name
        surname
        totalEvaluationScore
      }
      totalCount(filter: $filter)
    }
  }
`;

export default USER_PAGINATE;
