import { gql } from 'apollo-boost';

const ADD_RECEIVER_MUTATION = gql`
  mutation addReceiverMutation(
    $timeNotificationReceiverGroup: Int!
    $timeNotificationReceiver: TimeNotificationReceiverArg!
  ) {
    timeNotificationReceiverGroupAddTimeNotificationReceiver(
      timeNotificationReceiverGroupId: $timeNotificationReceiverGroup
      timeNotificationReceiver: $timeNotificationReceiver
    ) {
      id
      receivers {
        id
      }
    }
  }
`;

export default ADD_RECEIVER_MUTATION;
