import { gql } from 'apollo-boost';

const ACTION_HISTORY_FIND_BY_ID = gql`
  query($id: Int!) {
    actionHistoryFindById(id: $id) {
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
  }
`;

export default ACTION_HISTORY_FIND_BY_ID;
