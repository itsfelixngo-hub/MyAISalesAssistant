import { Injectable } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { Token } from './token.schema';
import * as bcrypt from 'bcrypt';
import { ExErrorException } from 'src/common/error.filter';

@Injectable()
export class TokenService {
    constructor(
        @InjectModel(Token.name) private tokenModel: Model<Token>
    ) { }

    async saveRefreshToken(userId: string, refreshToken: string) {
        try {
            return this.tokenModel.create({ userId, refreshToken });

        } catch (error) {
            throw new ExErrorException('REFRESH_TOKEN_FAIL', 401);
        }
    }

    async revokeToken(refreshToken: string) {
        const token = await this.findValidToken(refreshToken);

        if (!token) {
            throw new ExErrorException('TOKEN_NOT_FOUND', 403);
        }

        // Revoke the token
        await this.tokenModel.updateOne(
            { _id: token._id },
            { $set: { revoked: true } }
        ).exec();
    }

    async findValidToken(refreshToken: string) {
        const tokens = await this.tokenModel.find({ revoked: false }).exec();

        for (const token of tokens) {
            if (await bcrypt.compare(refreshToken, token.refreshToken)) {
                return token; // Return the valid token
            }
        }

        return null; // Return null if no valid token found
    }



    async revokeTokenAll(userId: string) {
        await this.tokenModel.updateMany(
            { userId },
            { revoked: true }
        );
    }

    async isTokenRevoked(refreshToken: string): Promise<boolean> {
        const tokens = await this.tokenModel.find({ revoked: true }).exec(); // Fetch only revoked tokens

        for (const token of tokens) {
            if (await bcrypt.compare(refreshToken, token.refreshToken)) {
                return true; // The token is revoked
            }
        }
        return false; // Token is not revoked
    }

}