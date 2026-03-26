import { Field, InputType } from 'type-graphql';

@InputType()
class ActionHistoryFilterArg {
  @Field(() => [String], { nullable: true, defaultValue: [] })
  name?: string[];

  @Field({ nullable: true, defaultValue: '' })
  userName?: string;

  @Field({ nullable: true, defaultValue: '' })
  userSurname?: string;

  @Field({ nullable: true })
  date?: Date;
}

export default ActionHistoryFilterArg;

export const getActionHistoryFilterArgDefaultValue = () => {
  const defaultValue = new ActionHistoryFilterArg();
  defaultValue.name = [];
  defaultValue.userName = '';
  defaultValue.userSurname = '';

  return defaultValue;
};
