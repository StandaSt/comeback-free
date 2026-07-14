import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1607675737454 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<void> {
        await addResourceCategories(queryRunner, [{name: "NOTIFICATIONS", label: "Administrace - Notifikace"}]);
        await addResources(queryRunner, [{
            name: "NOTIFICATIONS_SEE",
            categoryName: "NOTIFICATIONS",
            label: "Zobrazení",
            description: "Zobrazení notifikací"
        }, {
            name: "NOTIFICATIONS_EVENT_SEE",
            categoryName: "NOTIFICATIONS",
            label: "Zobrazení událostních notifikací",
            description: "Zobrazení událostních notiofikací",
            requiredResource: ["NOTIFICATIONS_SEE"]
        }, {
            name: "NOTIFICATIONS_EVENT_EDIT",
            categoryName: "NOTIFICATIONS",
            label: "Upravování událostních notifikací",
            description: "Upravování událostních notifikací",
            requiredResource: ["NOTIFICATIONS_EVENT_SEE"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await removeResources(queryRunner, ["NOTIFICATIONS_SEE", "NOTIFICATIONS_EVENT_SEE", "NOTIFICATIONS_EVENT_EDIT"]);
        await removeResourceCategories(queryRunner, ["NOTIFICATIONS"]);
    }

}
