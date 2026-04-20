import { gql } from 'apollo-boost';

const EVENT_NOTIFICATION_EDIT = gql`
  mutation EventNotificationEdit($id: Int!, $message: String!) {
    eventNotificationEdit(id: $id, message: $message) {
      id
      message
    }
  }
`;

export default EVENT_NOTIFICATION_EDIT;
