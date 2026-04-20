import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1589819312926 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "NEWS", label: "Novinky"}])
        await addResources(queryRunner, [{
            name: "NEWS_SEE",
            categoryName: "NEWS",
            label: "Zobrazení novinek",
            description: "Může si zobrazit novinky"
        }])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["NEWS_SEE"])
        await removeResourceCategories(queryRunner, ["NEWS"])
    }

}
