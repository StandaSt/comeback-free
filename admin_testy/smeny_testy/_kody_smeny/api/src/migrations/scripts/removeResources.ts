import {QueryRunner} from "typeorm";
import removeResource from "./removeResource";

const removeResources = async (queryRunner: QueryRunner, resources: string[]) => {
    for (const resource of resources) {
        await removeResource(queryRunner, resource)
    }
};

export default removeResources