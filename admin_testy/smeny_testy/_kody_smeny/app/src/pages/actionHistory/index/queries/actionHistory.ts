import { gql } from 'apollo-boost';

const ACTION_HISTORY_PAGINATE = gql`
  query(
    $limit: Int!
    $offset: Int!
    $orderBy: OrderByArg
    $filter: ActionHistoryFilterArg
  ) {
    actionHistoryPaginate {
      items(
        limit: $limit
        offset: $offset
        orderBy: $orderBy
        filter: $filter
      ) {
        id
        name
        date
        additionalData
        user {
          id
          name
          surname
        }
      }
      totalCount
    }
  }
`;

export default ACTION_HISTORY_PAGINATE;
