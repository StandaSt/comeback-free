import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class EditResources1589109070064 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "BRANCH_ASSIGN_WORKER",
            label: "Přiřazení pracovníků k pobočce",
            categoryName: "BRANCH",
            description: "Přiřazení pracovníků k pobočce.",
            requiredResource: ["BRANCH_SEE_ALL"]
        }])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["BRANCH_ASSIGN_WORKER"])
    }

}
