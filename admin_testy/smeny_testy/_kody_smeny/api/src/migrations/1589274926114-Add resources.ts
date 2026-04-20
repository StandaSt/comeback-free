import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1589274926114 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "PREFERRED_WEEK_SEE_ALL",
            categoryName: "PREFERRED_WEEK",
            label: "Zobrazení všech požadavků",
            description: "Může zobrazit všechny požadavky v systému.",
            requiredResource: ["USER_SEE_ALL"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["PREFERRED_WEEK_SEE_ALL"]);
    }

}
