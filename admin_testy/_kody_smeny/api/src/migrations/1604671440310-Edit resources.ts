import {MigrationInterface, QueryRunner} from "typeorm";
import removeResources from "./scripts/removeResources";
import addResources from "./scripts/addResources";

export class EditResources1604671440310 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["EVALUATION_SEE"]);
        await addResources(queryRunner, [{
            name: "EVALUATION_HISTORY",
            categoryName: "EVALUATION",
            label: "Zobrazení historie",
            description: "Zobrazení historie hodnocení"
        }, {
            name: "EVALUATION_ADD",
            categoryName: "EVALUATION",
            label: "Přidávání",
            description: "Přidávání hodnocení"
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "EVALUATION_SEE",
            categoryName: "EVALUATION",
            label: "Zobrazení",
            description: "Zobrazení hodnocení"
        }]);
        await removeResources(queryRunner, ["EVALUATION_HISTORY", "EVALUATION_ADD"]);
    }

}
