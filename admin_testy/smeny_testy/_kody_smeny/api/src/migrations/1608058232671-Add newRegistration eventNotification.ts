import {MigrationInterface, QueryRunner} from "typeorm";
import addEventNotifications from "./scripts/addEventNotifications";
import removeEventNotifications from "./scripts/removeEventNotifications";

export class AddNewRegistrationEventNotification1608058232671 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<void> {
        await addEventNotifications(queryRunner, [{
            eventName: "newUserRegistration",
            message: "Nový uživatel se registroval do systému",
            label: "Registrace nového uživatele",
            description: "Po registraci nového uživatele odešle notifikaci všem co mají potřebný zdroj.",
            variables: [
                {
                    value: "name",
                    description: "Jméno"
                },
                {value: "surname", description: "Příjmení"},
                {value: "email", description: "Email"}
            ]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await removeEventNotifications(queryRunner, ["newUserRegistration"]);
    }

}
