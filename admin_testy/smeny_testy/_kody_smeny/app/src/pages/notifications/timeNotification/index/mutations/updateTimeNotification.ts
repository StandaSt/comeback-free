import { gql } from 'apollo-boost';

const UPDATE_TIME_NOTIFICATION_MUTATION = gql`
  mutation(
    $id: Int!
    $name: String!
    $message: String!
    $repeat: Repeat!
    $date: DateTime
  ) {
    timeNotificationUpdate(
      id: $id
      name: $name
      message: $message
      repeat: $repeat
      date: $date
    ) {
      id
      name
      message
      repeat
      date
    }
  }
`;

export default UPDATE_TIME_NOTIFICATION_MUTATION;
