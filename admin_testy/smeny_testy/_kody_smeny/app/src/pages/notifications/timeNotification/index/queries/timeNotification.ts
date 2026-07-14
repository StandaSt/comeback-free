import { gql } from 'apollo-boost';

const TIME_NOTIFICATION_QUERY = gql`
  query($id: Int!) {
    timeNotificationFindById(id: $id) {
      id
      name
      repeat
      date
      message
      receiverGroups {
        id
        receivers {
          id
          resource {
            id
            label
            category {
              id
              label
            }
          }
          role {
            id
            name
          }
        }
      }
    }
  }
`;

export default TIME_NOTIFICATION_QUERY;
