import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategory from "./scripts/addResourceCategory";
import addResource from "./scripts/addResource";
import removeResource from "./scripts/removeResource";
import removeResourceCategory from "./scripts/removeResourceCategory";

export class AddResources1587063184813 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategory(queryRunner, "GLOBAL_SETTINGS", "Globální nastavení");
        await addResource(queryRunner, "GLOBAL_SETTINGS_CAN_EDIT",
            "Úprava globálního nastavení",
            "Může upravovat globální nastavení.",
            "GLOBAL_SETTINGS",
            0,
            []);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResource(queryRunner,"GLOBAL_SETTINGS_CAN_EDIT");
        await removeResourceCategory(queryRunner,"GLOBAL_SETTINGS");
    }
}
