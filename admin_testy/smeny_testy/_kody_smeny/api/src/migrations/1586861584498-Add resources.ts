import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import removeResourceCategories from "./scripts/removeResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1586861584498 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "PREFERRED_WEEK", label: "Požadavky"}]);

        await addResources(queryRunner, [
            {
                name: "PREFERRED_WEEK_CAN_PREFER",
                label: "Volba požadavků",
                description: "Může volit požadavky a může být přiřazen do směn.",
                categoryName: "PREFERRED_WEEK",
                minimalCount: 0
            }
        ])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner,["PREFERRED_WEEK_CAN_PREFER"]);
        await removeResourceCategories(queryRunner, ["PREFERRED_WEEK"])
    }
}
