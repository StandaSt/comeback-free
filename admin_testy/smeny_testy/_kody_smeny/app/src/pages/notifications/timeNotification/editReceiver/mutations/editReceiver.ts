import { gql } from 'apollo-boost';

const EDIT_RECEIVER_MUTATION = gql`
  mutation EditReceiverMutation(
    $id: Int!
    $timeNotificationReceiver: TimeNotificationReceiverArg!
  ) {
    timeNotificationReceiverEdit(
      id: $id
      timeNotificationReceiver: $timeNotificationReceiver
    ) {
      id
    }
  }
`;

export default EDIT_RECEIVER_MUTATION;
