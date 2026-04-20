import {QueryRunner} from 'typeorm';
import ResourceCategory from 'resourceCategory/resourceCategory.entity';
import Resource from 'resource/resource.entity';

const removeResource = async (queryRunner: QueryRunner, resourceName: string) => {
    const resourceRepository = queryRunner.manager
        .getRepository(Resource);
    const resource = await resourceRepository
        .findOne({name: resourceName});
    await resourceRepository.remove(resource)
};

export default removeResource;
