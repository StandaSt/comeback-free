import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResourceCategories from "./scripts/removeResourceCategories";
import removeResources from "./scripts/removeResources";

export class AddResources1604564684588 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "EVALUATION", label: "Hodnocení"}]);
        await addResources(queryRunner, [{
            name: "EVALUATION_SEE",
            categoryName: "EVALUATION",
            label: "Zobrazení",
            description: "Zobrazení hodnocení"
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["EVALUATION_SEE"]);
        await removeResourceCategories(queryRunner, ["EVALUATION"]);
    }
}
