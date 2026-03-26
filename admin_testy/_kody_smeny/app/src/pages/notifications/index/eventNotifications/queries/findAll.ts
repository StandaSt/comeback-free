import { gql } from 'apollo-boost';

const EVENT_NOTIFICATIONS_FIND_ALL = gql`
  {
    eventNotificationFindAll {
      id
      label
      description
      message
    }
  }
`;

export default EVENT_NOTIFICATIONS_FIND_ALL;
