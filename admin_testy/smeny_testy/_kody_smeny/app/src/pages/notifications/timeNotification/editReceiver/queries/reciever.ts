import { gql } from 'apollo-boost';

const RECEIVER_QUERY = gql`
  query ReceiverQuery($id: Int!) {
    timeNotificationReceiverFindById(id: $id) {
      id
      role {
        id
      }
      resource {
        id
      }
    }
  }
`;

export default RECEIVER_QUERY;
