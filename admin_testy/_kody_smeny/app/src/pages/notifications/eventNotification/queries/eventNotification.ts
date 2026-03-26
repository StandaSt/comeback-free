import { gql } from 'apollo-boost';

const EVENT_NOTIFICATION_FIND_BY_ID = gql`
  query EventNotificationsFindById($id: Int!) {
    eventNotificationFindById(id: $id) {
      id
      label
      message
      description
      variables {
        value
        description
      }
    }
  }
`;

export default EVENT_NOTIFICATION_FIND_BY_ID;
