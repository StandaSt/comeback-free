import { gql } from 'apollo-boost';

const TIME_NOTIFICATIONS_QUERY = gql`
  {
    timeNotificationFindAll {
      id
      name
    }
  }
`;

export default TIME_NOTIFICATIONS_QUERY;
