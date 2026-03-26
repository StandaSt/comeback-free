import { Field, InputType } from 'type-graphql';

@InputType()
class UserFilterArg {
  @Field({ nullable: true, defaultValue: '' })
  email?: string = '';

  @Field({ nullable: true, defaultValue: '' })
  name?: string;

  @Field({ nullable: true, defaultValue: '' })
  surname?: string;

  @Field(() => [Boolean], { nullable: true, defaultValue: [true, false] })
  active: boolean[];

  @Field(() => [Boolean], { nullable: true, defaultValue: [true, false] })
  approved: boolean[];

  @Field(() => [Boolean], { nullable: true, defaultValue: [true, false] })
  notificationsActivated: boolean[];
}

export default UserFilterArg;

export const getUserFilterArgDefaultValue = () => {
  const defaultValue = new UserFilterArg();
  defaultValue.email = '';
  defaultValue.name = '';
  defaultValue.surname = '';
  defaultValue.active = [true, false];
  defaultValue.approved = [true, false];
  defaultValue.notificationsActivated = [true, false];

  return defaultValue;
};
