import { Injectable } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { User } from './user.schema';
import { RegisterDto } from '../auth/dto/register.dto';
import { Role } from '../auth/roles.enum';
import { ExErrorException } from 'src/common/error.filter';
import { AvatarService } from '../services/avatar.service';

@Injectable()
export class UsersService {
  constructor(
    @InjectModel(User.name) private userModel: Model<User>,
    private readonly avatarService: AvatarService
  ) { }

  async profileById(_id: string): Promise<User | null> {
    return this.userModel.findOne({ _id }).lean().exec();
  }

  async findByEmail(email: string): Promise<User | null> {
    return this.userModel.findOne({ email }).exec();
  }

  async checkRole(email: string): Promise<Role | null> {
    const admin = await this.userModel.findOne({ email }, 'role').exec();

    if (!admin) {
      throw new ExErrorException('ADMIN_NOT_FOUND', 404);
    }
    return admin.role;
  }

  async createUser(registerDto: RegisterDto): Promise<User> {
    const creator = await this.userModel.findOne({ email: registerDto.createBy }, 'role').exec();
    if (!creator) {
        throw new ExErrorException('ADMIN_NOT_FOUND', 404);
    }
    const newRole = registerDto?.role || Role.Member;
    if (!Object.values(Role).includes(newRole)) {
        throw new ExErrorException('ROLE_NOT_EXIST', 404);
    }

    try {
      const isRole = creator.role;

      if(isRole < newRole) {
        throw new ExErrorException('PERMISSION_ROLE', 403);
      }

      if(!registerDto.avatar) {
        registerDto.avatar = (await this.avatarService.getAvatarBase64(registerDto.email))?.base64;
      }
      // console.debug(`avatar: ${avatar}`);
      const newUser = new this.userModel({...registerDto, status: 1});
      return newUser.save();
    } catch (error) {
      throw new ExErrorException(
        'USER_REGISTER_FAIL',
        500,
        error.message,
        error
        );
    }
  }
}