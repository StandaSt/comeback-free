import {MigrationInterface, QueryRunner} from "typeorm";
import addEventNotifications from "./scripts/addEventNotifications";
import removeEventNotifications from "./scripts/removeEventNotifications";

export class AddShiftWeekPublishEventNotification1607675688258 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<void> {
        await addEventNotifications(queryRunner, [{
            eventName: "shiftWeekPublish",
            message: "Směny publikovány",
            label: "Publikování směn",
            description: "Odešle notifikaci pracovníkům, kteří mají všechny přiřazené pobočky publikované. Notifikace se pošle pouze jednou.",
            variables: [
                {
                    value: "weekFrom",
                    description: "Datum začátku publikovaného týdne ve formátu DD. MM."
                },
                {value: "weekTo", description: "Datum konce publikovaného týdne ve formátu DD. MM."}
            ]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await removeEventNotifications(queryRunner, ["shiftWeekPublish"]);
    }

}
